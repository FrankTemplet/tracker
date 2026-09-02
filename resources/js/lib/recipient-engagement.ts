/**
 * Recipient subsets a metric can be drilled down into.
 *
 * Mirrors DataverseService::ENGAGEMENT_FILTERS on the backend.
 */
export type RecipientEngagementFilter = 'delivered' | 'hard-bounced' | 'clicked';

export const ENGAGEMENT_DESCRIPTION: Record<RecipientEngagementFilter, string> = {
    delivered: 'Recipients this email was delivered to',
    'hard-bounced': 'Recipients this email hard bounced for',
    clicked: 'Recipients who clicked this email',
};

export const ENGAGEMENT_EMPTY_STATE: Record<RecipientEngagementFilter, string> = {
    delivered: 'No delivered recipients in the engagement log for this send',
    'hard-bounced': 'No hard-bounced recipients in the engagement log for this send',
    clicked: 'No recipients with clicks in the engagement log for this send',
};

/**
 * Whether a send has rows behind one of its metrics in the recipient log.
 *
 * The log is fed separately from Power BI and holds far fewer rows than the
 * metrics report: as of this writing 5 of the 64 sends it covers have any
 * hard-bounce row at all. Without this per-subset check a send showing 12 hard
 * bounces still opens an empty modal, which reads as a bug.
 *
 * `null` or `undefined` means the coverage list could not be read, in which
 * case the drill-down stays offered rather than being hidden on a transient
 * Dataverse failure.
 */
export function hasRecipientCoverage(
    coverage: string[] | null | undefined,
    engagement: RecipientEngagementFilter,
): boolean {
    return coverage == null || coverage.includes(engagement);
}
