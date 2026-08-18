<?php

namespace App\Http\Controllers\Concerns;

/**
 * Campaign access checks shared by the controllers that expose campaign data.
 *
 * Requires a $powerBiService property on the using class.
 */
trait AuthorizesCampaigns
{
    /**
     * Determine if a campaign name starts with one of the given regions.
     *
     * @param  list<string>  $allowedRegions
     */
    protected function campaignNameInRegions(string $campaignName, array $allowedRegions): bool
    {
        $name = strtolower($campaignName);

        foreach ($allowedRegions as $region) {
            if (str_starts_with($name, $region)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a campaign belongs to one of the user's allowed regions.
     *
     * @param  list<string>  $allowedRegions
     */
    protected function campaignIsAllowed(string $campaignId, array $allowedRegions): bool
    {
        $campaignName = $this->campaignName($campaignId);

        return $campaignName !== null
            && $this->campaignNameInRegions($campaignName, $allowedRegions);
    }

    /**
     * Find the name of a campaign, or null when it does not exist.
     */
    protected function campaignName(string $campaignId): ?string
    {
        foreach ($this->powerBiService->getUniqueCampaigns() as $campaign) {
            if (($campaign['campaign_id'] ?? '') === $campaignId) {
                return $campaign['campaign_name'] ?? '';
            }
        }

        return null;
    }
}
