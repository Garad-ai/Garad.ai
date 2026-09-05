<?php

namespace App\Extensions\MegaMenu\System\Http\Controllers;

use App\Extensions\MegaMenu\System\Http\Requests\MegaMenuItemRequest;
use App\Extensions\MegaMenu\System\Models\MegaMenu;
use App\Extensions\MegaMenu\System\Models\MegaMenuItem;
use App\Extensions\MegaMenu\System\Services\MegaMenuService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MegaMenuItemController extends Controller
{
    public function __construct(
        public MegaMenuService $service
    ) {}

    public function index(MegaMenu $megaMenu): View
    {
        return view('mega-menu::items', [
            'items'                     => $megaMenu->items,
            'megaMenu'                  => $megaMenu,
            'current_number_of_columns' => $megaMenu->items->where('type', 'divider')->count(),
        ]);
    }

    public function order(Request $request, MegaMenu $megaMenu): JsonResponse
    {

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $data = $request->get('menu');

        $parentMenuKeys = array_keys(
            array_filter($data, fn ($v) => $v === 'null')
        );

        $this->service->parentMenuOrderUpdate(
            $megaMenu->id,
            $parentMenuKeys
        );

        $this->service->subMenuOrderUpdate(
            $megaMenu->id,
            array_filter($data, fn ($v) => $v !== 'null')
        );

        return response()->json([
            'status'  => 'success',
            'message' => trans('Menu updated'),
        ]);
    }

    public function store(MegaMenuItemRequest $request, MegaMenu $megaMenu): JsonResponse
    {
        if ($request['type'] === 'divider') {
            $current_number_of_columns = $megaMenu->items->where('type', 'divider')->count();

            $number_of_columns = $megaMenu->number_of_columns - 1;

            if ($number_of_columns <= $current_number_of_columns) {
                return response()->json([
                    'status'  => 'error',
                    'message' => trans('Number of columns is full'),
                ]);
            }
        }

        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        MegaMenuItem::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Item created successfully',
        ]);
    }

    public function status(MegaMenu $megaMenu, MegaMenuItem $megaMenuItem): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $megaMenuItem->update(['is_active' => ! $megaMenuItem->getAttribute('is_active')]);

        return response()->json([
            'message' => 'Item status updated successfully',
        ]);
    }

    public function upload(Request $request, MegaMenu $megaMenu, MegaMenuItem $megaMenuItem): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
        ]);

        $icon = $request->file('file')?->store('menus', 'public');

        $megaMenuItem->update(['icon' => $icon]);

        return response()->json([
            'message' => trans('Icon uploaded successfully'),
            'url'     => Storage::disk('uploads')->url($icon),
        ]);
    }

    public function update(Request $request, MegaMenu $megaMenu, MegaMenuItem $megaMenuItem, $type): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validate([
            'value' => 'required',
        ]);

        $megaMenuItem->update([$type => $request->get('value')]);

        return response()->json([
            'message' => trans('Item updated successfully'),
        ]);
    }

    public function destroy(Request $request, MegaMenu $megaMenu, MegaMenuItem $megaMenuItem): \Illuminate\Http\RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with([
                'success' => trans('This feature is disabled in demo mode.'),
                'type'    => 'error',
            ]);
        }

        $megaMenuItem->delete();

        return back()->with([
            'success' => 'Item deleted successfully',
            'type'    => 'success',
        ]);
    }

    public function numberOfColumns(Request $request, MegaMenu $megaMenu): JsonResponse
    {
        $request->validate(['number_of_columns' => 'required|integer|min:1|max:4']);

        $megaMenu->update(['number_of_columns' => $request->get('number_of_columns')]);

        return response()->json([
            'message' => trans('Number of columns updated successfully'),
            'status'  => true,
        ]);
    }
}
