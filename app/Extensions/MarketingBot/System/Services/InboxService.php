<?php

namespace App\Extensions\MarketingBot\System\Services;

use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InboxService
{
    public function agentConversations(?string $orderBy = null): Collection|array
    {
        return MarketingConversation::query()
            ->where('user_id', auth()->id())
            ->with(['histories.user:id,avatar', 'lastMessage'])
            ->when($orderBy, function (Builder $query) use ($orderBy) {
                $query->orderBy($orderBy ?: 'id', 'desc');
            })
            ->get();
    }

    public function agentConversationsWithPaginate(?string $orderBy = null): LengthAwarePaginator
    {
        return MarketingConversation::query()
            ->where('user_id', auth()->id())
            ->when(request('chatbot_channel') && request('chatbot_channel') !== 'all', function (Builder $query) {
                $query->where('type', request('chatbot_channel'));
            })
            ->with(['histories.user:id,avatar', 'lastMessage'])
            ->when($orderBy, function (Builder $query) use ($orderBy) {
                $query->orderBy($orderBy ?: 'id', 'desc');
            })
            ->paginate(request('per_page', request('perPage', 30)));
    }

    public function agentConversationsBySearch(string $search): Collection|array
    {
        return MarketingConversation::query()
            ->where('user_id', auth()->id())
            ->with(['histories.user:id,avatar', 'lastMessage'])
            ->whereNotNull('connect_agent_at')
            ->whereHas('histories', function (Builder $query) use ($search) {
                $query->where('message', 'like', "%$search%");
            })
            ->get();
    }
}
