<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Controllers;

use App\Extensions\PhoneCallAgent\System\Enums\TrainTypeEnum;
use App\Extensions\PhoneCallAgent\System\Http\Requests\Train\DataRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\Train\FileRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\Train\TextRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\Train\TrainRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\Train\TrainUrlRequest;
use App\Extensions\PhoneCallAgent\System\Http\Resources\PhoneCallAgentTrainResource;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentTrain;
use App\Extensions\PhoneCallAgent\System\Parsers\LinkParser;
use App\Extensions\PhoneCallAgent\System\Services\PhoneCallAgentService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Smalot\PdfParser\Parser as PdfParser;

class PhoneCallAgentTrainController extends Controller
{
    public function __construct(public PhoneCallAgentService $service) {}

    public function trainData(DataRequest $request): AnonymousResourceCollection
    {
        return PhoneCallAgentTrainResource::collection(
            $this->service->query()
                ->findOrFail($request->validated('id'))
                ?->trains()
                ->when($request->validated('type'), fn ($query) => $query->where('type', $request->validated('type')))
                ->get()
        );
    }

    public function delete(TrainRequest $request): JsonResponse
    {
        $agent = $this->service->query()->findOrFail($request->validated('id'));

        $trains = $agent->trains()->whereIn('id', $request->validated('data'))->get();
        foreach ($trains as $train) {
            if ($train->trained_at && $train->doc_id && $agent->provider->value !== 'twilio') {
                $train->trained_at = null;
                $train->save();

                $this->service->updateAgentWithKnowledgebase($agent);
                $this->service->deleteKnowledgebase($train->doc_id);
            }
            $train->delete();
        }

        return response()->json([
            'message' => 'Deleted successfully',
            'status'  => 200,
        ]);
    }

    public function trainFile(FileRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $agent = $this->service->query()->findOrFail($request->validated('id'));

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $defaultDisk = 'public';
        $path = $file->store('phone-call-agent', ['disk' => $defaultDisk]);

        ExtPhoneCallAgentTrain::create([
            'type'     => TrainTypeEnum::file,
            'name'     => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'user_id'  => Auth::id(),
            'agent_id' => $agent->getKey(),
            'file'     => $path,
        ]);

        return PhoneCallAgentTrainResource::collection(
            $agent->trains()->whereNotNull('file')->get()
        );
    }

    public function trainText(TextRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $agent = $this->service->query()->findOrFail($request->validated('id'));

        ExtPhoneCallAgentTrain::create([
            'type'     => TrainTypeEnum::text,
            'user_id'  => Auth::id(),
            'agent_id' => $agent->getKey(),
            'name'     => $request->validated('title'),
            'text'     => $request->validated('content'),
        ]);

        return PhoneCallAgentTrainResource::collection(
            $agent->trains()
                ->whereNull('file')
                ->whereNull('url')
                ->get()
        );
    }

    public function trainUrl(TrainUrlRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $agent = $this->service->query()->findOrFail($request->validated('id'));

        app(LinkParser::class)
            ->setBaseUrl($request->validated('url'))
            ->crawl((bool) $request->validated('single'))
            ->insertEmbeddings($agent);

        return PhoneCallAgentTrainResource::collection(
            $agent->trains()->whereNotNull('url')->get()
        );
    }

    public function generateEmbedding(TrainRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $agent = $this->service->query()->findOrFail($request->validated('id'));

        ini_set('max_execution_time', -1);

        $data = $request->validated('data');
        $trains = ExtPhoneCallAgentTrain::query()
            ->whereNull('trained_at')
            ->whereIn('id', $data)
            ->get();

        foreach ($trains as $train) {
            if ($agent->provider->value === 'twilio') {
                $extracted = $this->extractTextForTwilio($train);
                $train->update(['content' => $extracted, 'trained_at' => now()]);

                continue;
            }

            $content = '';
            if ($train->type === TrainTypeEnum::text || $train->type->value === 'text') {
                $content = $train->text;
            } elseif ($train->type === TrainTypeEnum::url || $train->type->value === 'url') {
                $content = $train->url;
            } elseif ($train->type === TrainTypeEnum::file || $train->type->value === 'file') {
                $defaultDisk = 'public';
                $path = $train->file;
                $storagePath = config('filesystems.disks.' . $defaultDisk . '.root') . '/' . $path;

                $content = new UploadedFile(
                    $storagePath,
                    basename($path),
                    mime_content_type($storagePath),
                    null,
                    true
                );
            }

            $res = $this->service->addKnowledgebase(
                is_string($train->type) ? $train->type : $train->type->value,
                $content,
                $train->name
            );

            if ($res->getData()->status === 'success') {
                $train->update([
                    'name'       => $res->getData()->resData?->name,
                    'doc_id'     => $res->getData()->resData?->id,
                    'trained_at' => now(),
                ]);
            }
        }

        if ($trains->isNotEmpty() && $agent->provider->value !== 'twilio') {
            $this->service->updateAgentWithKnowledgebase($agent);
        }

        return PhoneCallAgentTrainResource::collection($agent->trains()->whereIn('id', $data)->get());
    }

    private function extractTextForTwilio(ExtPhoneCallAgentTrain $train): string
    {
        $type = is_string($train->type) ? $train->type : $train->type->value;

        if ($type === 'text') {
            return $train->text ?? '';
        }

        if ($type === 'url') {
            $html = @file_get_contents($train->url ?? '');
            if ($html === false) {
                return '';
            }

            return preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';
        }

        if ($type === 'file') {
            $defaultDisk = 'public';
            $storagePath = config('filesystems.disks.' . $defaultDisk . '.root') . '/' . $train->file;

            if (! file_exists($storagePath)) {
                return '';
            }

            try {
                return (new PdfParser)->parseFile($storagePath)->getText();
            } catch (Exception) {
                return '';
            }
        }

        return '';
    }
}
