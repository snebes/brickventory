<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/vendors', name: 'api_vendors_')]
class VendorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $active = $request->query->get('active');
        $search = $request->query->get('search');
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 100);

        $qb = $this->entityManager
            ->getRepository(Vendor::class)
            ->createQueryBuilder('v')
            ->orderBy('v.vendorName', 'ASC');

        if ($active !== null) {
            $qb->andWhere('v.active = :active')
               ->setParameter('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search) {
            $qb->andWhere('v.vendorName LIKE :search OR v.vendorCode LIKE :search OR v.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Get total count for pagination
        $totalCount = (clone $qb)->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $vendors = $qb->getQuery()->getResult();

        $result = [
            'vendors' => array_map(fn(Vendor $v) => $this->serializeVendor($v), $vendors),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalCount,
                'pages' => (int) ceil($totalCount / $perPage),
            ],
        ];

        return $this->json($result);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $vendor = $this->entityManager->getRepository(Vendor::class)->find($id);
        
        if (!$vendor) {
            return $this->json(['error' => 'Vendor not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeVendor($vendor));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Check for duplicate vendor code
            $existing = $this->entityManager->getRepository(Vendor::class)
                ->findOneBy(['vendorCode' => $data['vendorCode'] ?? '']);
            
            if ($existing) {
                return $this->json(['error' => 'Vendor code already exists'], Response::HTTP_BAD_REQUEST);
            }

            $vendor = new Vendor();
            $this->updateVendorFromData($vendor, $data);

            $this->entityManager->persist($vendor);
            $this->entityManager->flush();

            return $this->json([
                'id' => $vendor->id,
                'message' => 'Vendor created successfully',
                'vendor' => $this->serializeVendor($vendor)
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $vendor = $this->entityManager->getRepository(Vendor::class)->find($id);
            
            if (!$vendor) {
                return $this->json(['error' => 'Vendor not found'], Response::HTTP_NOT_FOUND);
            }

            // Check for duplicate vendor code (excluding current vendor)
            if (isset($data['vendorCode']) && $data['vendorCode'] !== $vendor->vendorCode) {
                $existing = $this->entityManager->getRepository(Vendor::class)
                    ->findOneBy(['vendorCode' => $data['vendorCode']]);
                
                if ($existing) {
                    return $this->json(['error' => 'Vendor code already exists'], Response::HTTP_BAD_REQUEST);
                }
            }

            $this->updateVendorFromData($vendor, $data);
            $vendor->touch();

            $this->entityManager->flush();

            return $this->json([
                'id' => $id,
                'message' => 'Vendor updated successfully',
                'vendor' => $this->serializeVendor($vendor)
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $vendor = $this->entityManager->getRepository(Vendor::class)->find($id);
            
            if (!$vendor) {
                return $this->json(['error' => 'Vendor not found'], Response::HTTP_NOT_FOUND);
            }

            // Check if vendor has associated purchase orders
            // For now, we'll allow deletion - in production, consider soft delete
            $this->entityManager->remove($vendor);
            $this->entityManager->flush();

            return $this->json(['message' => 'Vendor deleted successfully']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Cannot delete vendor. It may have associated purchase orders or bills.'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function updateVendorFromData(Vendor $vendor, array $data): void
    {
        if (isset($data['vendorCode'])) {
            $vendor->vendorCode = $data['vendorCode'];
        }
        
        if (isset($data['vendorName'])) {
            $vendor->vendorName = $data['vendorName'];
        }
        
        if (isset($data['active'])) {
            $vendor->active = (bool) $data['active'];
        }
        
        if (isset($data['email'])) {
            $vendor->email = $data['email'] ?: null;
        }
        
        if (isset($data['phone'])) {
            $vendor->phone = $data['phone'] ?: null;
        }
        
        if (isset($data['website'])) {
            $vendor->website = $data['website'] ?: null;
        }
        
        if (isset($data['billingAddress'])) {
            $vendor->billingAddress = $data['billingAddress'];
        }
        
        if (isset($data['shippingAddress'])) {
            $vendor->shippingAddress = $data['shippingAddress'];
        }
        
        if (isset($data['defaultPaymentTerms'])) {
            $vendor->defaultPaymentTerms = $data['defaultPaymentTerms'] ?: null;
        }
        
        if (isset($data['defaultCurrency'])) {
            $vendor->defaultCurrency = $data['defaultCurrency'] ?: null;
        }
        
        if (isset($data['creditLimit'])) {
            $vendor->creditLimit = $data['creditLimit'] ? (float) $data['creditLimit'] : null;
        }
        
        if (isset($data['taxId'])) {
            $vendor->taxId = $data['taxId'] ?: null;
        }
        
        if (isset($data['taxExempt'])) {
            $vendor->taxExempt = (bool) $data['taxExempt'];
        }
    }

    private function serializeVendor(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'uuid' => $vendor->uuid,
            'vendorCode' => $vendor->vendorCode,
            'vendorName' => $vendor->vendorName,
            'active' => $vendor->active,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'website' => $vendor->website,
            'billingAddress' => $vendor->billingAddress,
            'shippingAddress' => $vendor->shippingAddress,
            'defaultPaymentTerms' => $vendor->defaultPaymentTerms,
            'defaultCurrency' => $vendor->defaultCurrency,
            'creditLimit' => $vendor->creditLimit,
            'taxId' => $vendor->taxId,
            'taxExempt' => $vendor->taxExempt,
            'createdAt' => $vendor->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $vendor->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
