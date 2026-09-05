<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Campaign;

use App\Extensions\MarketingBot\System\Http\Requests\Campaign\CampaignWhatsappRequest;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Contact;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Segment;
use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WhatsappCampaignController extends Controller
{
    public function index()
    {
        return view('marketing-bot::whatsapp-campaign.index', [
            'title' => __('Whatsapp Campaigns'),
            'items' => MarketingCampaign::query()
                ->withCount([
                    'embeddings' => function ($query) {
                        $query->whereNotNull('embedding');
                    },
                ])
                ->where('type', 'whatsapp')
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('marketing-bot::whatsapp-campaign.create', [
            'title'           => trans('New WhatsApp Campaign'),
            'action'          => route('dashboard.user.marketing-bot.whatsapp-campaign.store'),
            'method'          => 'POST',
            'item'            => new MarketingCampaign,
            'contacts'        => Contact::my()->get(),
            'segments'        => Segment::my()->get(),
            'selectedContact' => [],
            'selectedSegment' => [],
            'whatsapp'        => WhatsappChannel::query()->where('user_id', Auth::id())->first(),
        ]);
    }

    public function store(CampaignWhatsappRequest $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $data = $request->validated();

        MarketingCampaign::query()->create($data);

        return redirect()->route('dashboard.user.marketing-bot.whatsapp-campaign.index')
            ->with([
                'type'    => 'success',
                'message' => trans('Whatsapp campaign created'),
            ]);
    }

    public function edit(Request $request, MarketingCampaign $whatsappCampaign): View
    {
        $this->authorize('edit', $whatsappCampaign);

        return view('marketing-bot::whatsapp-campaign.create', [
            'title'           => trans('Edit WhatsApp Campaign'),
            'action'          => route('dashboard.user.marketing-bot.whatsapp-campaign.update', $whatsappCampaign->getKey()),
            'method'          => 'PUT',
            'item'            => $whatsappCampaign,
            'contacts'        => Contact::my()->get(),
            'segments'        => Segment::query()->where('user_id', Auth::id())->get(),
            'selectedContact' => $whatsappCampaign->getAttribute('contacts') ?: [],
            'selectedSegment' => $whatsappCampaign->getAttribute('segments') ?: [],
            'whatsapp'        => WhatsappChannel::query()->where('user_id', Auth::id())->first(),
        ]);
    }

    public function update(CampaignWhatsappRequest $request, MarketingCampaign $whatsappCampaign): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $this->authorize('update', $whatsappCampaign);

        $whatsappCampaign->update($request->validated());

        return redirect()->route('dashboard.user.marketing-bot.whatsapp-campaign.index')
            ->with([
                'type'    => 'success',
                'message' => trans('Whatsapp campaign updated'),
            ]);
    }

    public function destroy(MarketingCampaign $whatsappCampaign): JsonResponse
    {
        $this->authorize('delete', $whatsappCampaign);

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $whatsappCampaign->delete();

        return response()->json([
            'status'  => 'success',
            'message' => trans('Whatsapp campaign deleted'),
        ]);
    }

    public function show(MarketingCampaign $whatsappCampaign) {}
}
