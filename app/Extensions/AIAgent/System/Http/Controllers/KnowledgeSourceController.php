<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Models\AIAgentKnowledgeSource;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KnowledgeSourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = AIAgentKnowledgeSource::query()
            ->forUser(Auth::id())
            ->latest()
            ->get(['id', 'name', 'type', 'file_name', 'created_at'])
            ->map(fn (AIAgentKnowledgeSource $s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'type'      => $s->type,
                'file_name' => $s->file_name,
            ]);

        return response()->json(['data' => $sources]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['required', 'string', 'in:text,file'],
            'content' => ['required_if:type,text', 'nullable', 'string', 'max:100000'],
            'file'    => ['required_if:type,file', 'nullable', 'file', 'max:10240', 'mimes:txt,md,csv,json,pdf'],
        ]);

        $content = null;
        $fileName = null;

        if ($request->input('type') === 'file') {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            if (in_array($file->getMimeType(), ['application/pdf', 'application/x-pdf'])) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($file->get());
                $content = iconv('UTF-8', 'UTF-8//IGNORE', $pdf->getText());
            } else {
                $content = $file->get();
            }
        } else {
            $content = $request->input('content');
        }

        $source = AIAgentKnowledgeSource::query()->create([
            'user_id'   => Auth::id(),
            'name'      => $request->input('name'),
            'type'      => $request->input('type'),
            'content'   => $content,
            'file_name' => $fileName,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'        => $source->id,
                'name'      => $source->name,
                'type'      => $source->type,
                'file_name' => $source->file_name,
            ],
        ], 201);
    }

    public function destroy(AIAgentKnowledgeSource $knowledgeSource): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        if ($knowledgeSource->user_id !== Auth::id()) {
            abort(403);
        }

        $knowledgeSource->delete();

        return response()->json(['status' => 'success']);
    }
}
