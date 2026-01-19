/**
 * Parses a date string from the backend API format to HTML date input format.
 * Backend returns dates in 'Y-m-d H:i:s' format (e.g., "2024-01-15 10:30:00")
 * HTML date input requires 'YYYY-MM-DD' format.
 * 
 * @param dateString - The date string from the API (format: "YYYY-MM-DD HH:mm:ss")
 * @returns The date portion in 'YYYY-MM-DD' format, or today's date if input is invalid
 */
export function parseApiDateForInput(dateString: string | undefined | null): string {
  if (!dateString) {
    return new Date().toISOString().split('T')[0]
  }
  
  // Extract just the date part (before the space)
  const datePart = dateString.split(' ')[0]
  
  // Validate format (should be YYYY-MM-DD)
  if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
    return datePart
  }
  
  // Fallback to today's date if parsing fails
  return new Date().toISOString().split('T')[0]
}
