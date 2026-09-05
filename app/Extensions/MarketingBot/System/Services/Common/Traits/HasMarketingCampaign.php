<?php

namespace App\Extensions\MarketingBot\System\Services\Common\Traits;

use App\Extensions\MarketingBot\System\Models\MarketingCampaign;

trait HasMarketingCampaign
{
    public MarketingCampaign $marketingCampaign;

    public function getMarketingCampaign(): MarketingCampaign
    {
        return $this->marketingCampaign;
    }

    public function setMarketingCampaign(MarketingCampaign $marketingCampaign): static
    {
        $this->marketingCampaign = $marketingCampaign;

        return $this;
    }

    public function setMarketingCampaignById(int $id): static
    {
        $this->marketingCampaign = MarketingCampaign::query()->findOrFail($id);

        return $this;
    }
}
