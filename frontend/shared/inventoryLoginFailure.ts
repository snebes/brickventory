/** Keep backend failures distinct from unreachable hosts without exposing server diagnostics. */
export function inventoryLoginFailure(status?: number, connectionCode?: string) {
    if (
        ['UNABLE_TO_VERIFY_LEAF_SIGNATURE', 'SELF_SIGNED_CERT_IN_CHAIN', 'DEPTH_ZERO_SELF_SIGNED_CERT'].includes(
            connectionCode || '',
        )
    )
        return { statusCode: 502, statusMessage: 'The inventory service HTTPS certificate could not be verified.' }
    if (status === 401) return { statusCode: 401, statusMessage: 'The access token was not accepted.' }
    if (status === 403) return { statusCode: 403, statusMessage: 'The inventory service denied access.' }
    if (status === 404)
        return {
            statusCode: 502,
            statusMessage: 'The inventory API was not found. Check the configured service address.',
        }
    if (status && status >= 500)
        return {
            statusCode: 502,
            statusMessage: 'The inventory service returned a server error. Please contact your administrator.',
        }
    return { statusCode: 502, statusMessage: 'Cannot connect to the inventory service.' }
}
