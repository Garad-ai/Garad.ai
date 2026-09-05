<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\MarketingBot\System\Enums\EmbeddingTypeEnum;
use App\Extensions\MarketingBot\System\Http\Requests\Training\DataRequest;
use App\Extensions\MarketingBot\System\Http\Requests\Training\EmbedingRequest;
use App\Extensions\MarketingBot\System\Http\Requests\Training\FileRequest;
use App\Extensions\MarketingBot\System\Http\Requests\Training\QaRequest;
use App\Extensions\MarketingBot\System\Http\Requests\Training\TextRequest;
use App\Extensions\MarketingBot\System\Http\Requests\Training\TrainUrlRequest;
use App\Extensions\MarketingBot\System\Http\Resources\Training\MarketingEmbeddingResource;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\MarketingCampaignEmbedding;
use App\Extensions\MarketingBot\System\Parsers\ExcelParser;
use App\Extensions\MarketingBot\System\Parsers\LinkParser;
use App\Extensions\MarketingBot\System\Parsers\PdfParser;
use App\Extensions\MarketingBot\System\Parsers\TextParser;
use App\Extensions\MarketingBot\System\Services\EmbedingService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketingBotTrainController extends Controller
{
    public function train(MarketingCampaign $marketingCampaign): View
    {
        return view('marketing-bot::training.index', [
            'marketingCampaign' => $marketingCampaign,
        ]);
    }

    public function trainData(DataRequest $request): AnonymousResourceCollection
    {
        $marketingCampaign = MarketingCampaign::query()
            ->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        return MarketingEmbeddingResource::collection(
            $marketingCampaign
                ->embeddings()
                ->when($request->validated('type'), fn ($query) => $query->where('type', $request->validated('type')))
                ->get()
        );
    }

    public function deleteEmbedding(EmbedingRequest $request): JsonResponse
    {
        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        $marketingCampaign->embeddings()->whereIn('id', $request->validated('data'))->delete();

        return response()->json([
            'message' => 'Embedding deleted successfully',
            'status'  => 200,
        ]);
    }

    public function generateEmbedding(EmbedingRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        /**
         * @var MarketingCampaign $marketingCampaign
         */
        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        ini_set('max_execution_time', -1);

        $data = $request->validated('data');

        $embeddings = MarketingCampaignEmbedding::query()
            ->whereNull('embedding')
            ->whereIn('id', $data)
            ->get();

        $aiEmbeddingModel = EntityEnum::TEXT_EMBEDDING_3_SMALL;

        if (is_null($marketingCampaign->getAttribute('ai_embedding_model'))) {
            $marketingCampaign->update([
                'ai_embedding_model' => EntityEnum::TEXT_EMBEDDING_3_SMALL->value,
            ]);
        }

        foreach ($embeddings as $embedding) {
            $embeddingJson = app(EmbedingService::class)
                ->setMarketingCampaign($marketingCampaign)
                ->setEntity($aiEmbeddingModel)
                ->generateEmbedding($embedding->getAttribute('content'));

            $embedding->update([
                'embedding'    => $embeddingJson->toArray(),
                'trained_at'   => now(),
            ]);
        }

        return MarketingEmbeddingResource::collection($marketingCampaign->embeddings()->get());
    }

    public function trainText(TextRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        MarketingCampaignEmbedding::query()
            ->create([
                'type'                  => EmbeddingTypeEnum::text,
                'marketing_campaign_id' => $marketingCampaign->getKey(),
                'url'                   => null,
                'file'                  => null,
                'engine'                => EngineEnum::OPEN_AI->value,
                'title'                 => $request->validated('title'),
                'content'               => $request->validated('content'),
            ]);

        return MarketingEmbeddingResource::collection(
            $marketingCampaign->embeddings()
                ->wherenull('file')
                ->whereNull('url')->get()
        );
    }

    public function trainQa(QaRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        MarketingCampaignEmbedding::query()
            ->create([
                'type'                  => EmbeddingTypeEnum::qa,
                'marketing_campaign_id' => $marketingCampaign->getKey(),
                'url'                   => null,
                'file'                  => null,
                'engine'                => EngineEnum::OPEN_AI->value,
                'title'                 => $request->validated('question'),
                'content'               => $request->validated('question') . ' : ' . $request->validated('answer'),
            ]);

        return MarketingEmbeddingResource::collection(
            $marketingCampaign->embeddings()
                ->wherenull('file')
                ->whereNull('url')->get()
        );
    }

    public function trainUrl(TrainUrlRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        /**
         * @var MarketingCampaign $marketingCampaign
         */
        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        $marketingCampaign->setAttribute('engine', EngineEnum::OPEN_AI->value);

        app(LinkParser::class)
            ->setBaseUrl($request->validated('url'))
            ->crawl((bool) $request->validated('single'))
            ->insertEmbeddings($marketingCampaign);

        return MarketingEmbeddingResource::collection(
            $marketingCampaign->embeddings()->whereNotNull('url')->get()
        );
    }

    public function trainFile(FileRequest $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $marketingCampaign = MarketingCampaign::query()->findOrFail($request->validated('id'));

        $this->authorize('train', $marketingCampaign);

        $file = $request->file('file');

        $extension = $file->guessExtension();

        $defaultDisk = 'public';

        $path = $file->store('chatbot', ['disk' => $defaultDisk]);

        $name = $file->getClientOriginalName();

        $storagePath = config('filesystems.disks.' . $defaultDisk . '.root') . '/' . $path;

        $parser = match (true) {
            in_array($extension, ['xlsx', 'xls', 'csv']) => app(ExcelParser::class),
            in_array($extension, ['txt', 'json'])        => app(TextParser::class),
            default                                      => app(PdfParser::class),
        };

        $text = $parser->setPath($storagePath)->parse();

        MarketingCampaignEmbedding::query()
            ->firstOrCreate([
                'type'                  => EmbeddingTypeEnum::file,
                'marketing_campaign_id' => $marketingCampaign->getKey(),
                'url'                   => null,
                'file'                  => $path,
                'engine'                => EngineEnum::OPEN_AI->value,
            ], [
                'title'    => $name,
                'content'  => $text,
            ]);

        return MarketingEmbeddingResource::collection(
            $marketingCampaign->embeddings()->whereNotNull('file')->get()
        );
    }
}
