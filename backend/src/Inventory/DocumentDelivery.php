<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Dto\Inventory\DocumentEmailPayload;
use App\Entity\Inventory\Document;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class DocumentDelivery
{
    public function __construct(
        private InventoryService $inventory,
        private MailerInterface $mailer,
        private ManagerRegistry $registry,
        #[Autowire(env: 'INVENTORY_MAIL_FROM')]
        private string $sender,
    ) {
    }

    public function pdf(Document $document): string
    {
        $view = $this->inventory->view($document);
        $escape = static fn (mixed $value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = 'orders' === $document->kind ? 'Invoice' : 'Purchase order';
        $contact = $document->customer?->name ?? $document->supplier?->name ?? $document->details['customer_name'] ?? '';
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:11px}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #ccc;text-align:left}</style></head><body><h1>'.$title.'</h1><p>'.$escape($document->number).'</p><p>'.$escape($contact).'</p><table><tr><th>Product</th><th>Quantity</th><th>Unit price</th></tr>';
        foreach ($document->items as $line) {
            $product = $this->inventory->getProduct($line['product_variant_id'] ?? $line['product_id']);
            $html .= '<tr><td>'.$escape($product->sku.' '.$product->name).'</td><td>'.$line['quantity'].'</td><td>'.$escape($line['unit_price']).'</td></tr>';
        }
        $html .= '</table>';
        foreach (['subtotal', 'discount', 'tax', 'shipping', 'total'] as $field) {
            $html .= '<p>'.$escape(ucfirst($field)).': '.$escape($view[$field]).' '.$escape($document->details['currency'] ?? '').'</p>';
        }
        $html .= '<p>'.$escape($document->details['notes'] ?? '').'</p></body></html>';
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function sendPurchaseOrder(int $id, DocumentEmailPayload $options): Document
    {
        return $this->registry->getManager()->wrapInTransaction(function () use ($id, $options) {
            $document = $this->inventory->action('purchase-orders', $id, 'send', []);
            $this->email($document, $options);

            return $document;
        });
    }

    public function email(Document $document, DocumentEmailPayload $options = new DocumentEmailPayload()): void
    {
        $recipient = $options->to ?? ('orders' === $document->kind ? ($document->details['customer_email'] ?? $document->customer?->email) : $document->supplier?->email);
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException('Document contact must have a valid email.');
        }
        if (!$this->sender || !filter_var($this->sender, FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException('INVENTORY_MAIL_FROM is not configured.');
        }
        if (!empty($document->details['approval_required']) && ($document->details['approval_status'] ?? '') !== 'approved') {
            throw new UnprocessableEntityHttpException('Document requires approval.');
        }
        $email = (new Email())->from($this->sender)->to($recipient)->subject(('orders' === $document->kind ? 'Invoice ' : 'Purchase order ').$document->number)->cc(...$options->cc)->text($options->message ?: 'Please find the document attached.')->attach($this->pdf($document), $document->number.'.pdf', 'application/pdf');
        $this->mailer->send($email);
    }
}
