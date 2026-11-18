export interface ProductImport {
    id: string
    filename: string
    status: 'queued' | 'running' | 'retrying' | 'completed' | 'failed' | 'cancelled'
    processed: number
    bytes_processed: number
    bytes_total: number
    progress: number
    error: { row: number | null; message: string } | null
    created_at: string
    updated_at: string
}
export const importIsActive = (job: ProductImport) => ['queued', 'running', 'retrying'].includes(job.status)
