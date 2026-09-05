<?php

namespace App\Extensions\CreativeSuite\System\Http\Controllers;

use App\Extensions\CreativeSuite\System\Http\Requests\CreativeSuiteDocumentRequest;
use App\Extensions\CreativeSuite\System\Http\Resources\CreativeSuiteDocumentResource;
use App\Extensions\CreativeSuite\System\Models\CreativeSuiteDocument;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreativeSuiteDocumentController extends Controller
{
    public function updateOrCreate(CreativeSuiteDocumentRequest $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $id = $request->input('id');

        if ($id) {
            $document = CreativeSuiteDocument::findOrFail($id);

            $document->update([
                'name'    => $request->name,
                'payload' => $request->payload,
            ]);

            if ($request->hasFile('preview')) {
                $document->update([
                    'preview' => $request->file('preview')?->store('documents/previews', 'uploads'),
                ]);
            }

            return CreativeSuiteDocumentResource::make($document);
        }

        $document = CreativeSuiteDocument::create([
            'user_id' => auth()->id(),
            'uuid'    => (string) Str::uuid(),
            'name'    => $request->name,
            'preview' => $request->file('preview')?->store('documents/previews', 'uploads'),
            'payload' => $request->payload,
        ]);

        return CreativeSuiteDocumentResource::make($document)->additional([
            'status'  => 'success',
            'message' => trans('Document save successfully.'),
        ]);
    }

    public function show(CreativeSuiteDocument $document): CreativeSuiteDocumentResource
    {
        return CreativeSuiteDocumentResource::make($document)->additional([
            'status' => 'success',
        ]);
    }

    public function duplicate(Request $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate(['id' => 'required']);

        $document = CreativeSuiteDocument::findOrFail($request->id);

        $newDocument = $document->replicate();
        $newDocument->uuid = (string) Str::uuid();
        $newDocument->name = $document->name . ' (Copy)';
        $newDocument->save();

        return CreativeSuiteDocumentResource::make($newDocument)->additional([
            'status' => 'success',
        ]);
    }

    public function name(Request $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'id'   => 'required',
            'name' => 'required|string|max:255',
        ]);

        $document = CreativeSuiteDocument::findOrFail($request->id);

        $document->update(['name' => $request->name]);

        return CreativeSuiteDocumentResource::make($document)->additional([
            'status' => 'success',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate(['id' => 'required']);

        $document = CreativeSuiteDocument::findOrFail($request->id);

        $document->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Document deleted successfully.',
        ]);
    }
}
