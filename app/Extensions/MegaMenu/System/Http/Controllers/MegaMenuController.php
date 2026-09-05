<?php

namespace App\Extensions\MegaMenu\System\Http\Controllers;

use App\Extensions\MegaMenu\System\Http\Requests\MegaMenuRequest;
use App\Extensions\MegaMenu\System\Models\MegaMenu;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class MegaMenuController extends Controller
{
    public function index()
    {
        return view('mega-menu::index', [
            'items' => MegaMenu::query()->paginate(10),
        ]);
    }

    public function create()
    {
        return view('mega-menu::edit', [
            'item'	  => new MegaMenu,
            'title'  => trans('Create Mega Menu'),
            'method' => 'POST',
            'action' => route('dashboard.admin.mega-menu.store'),
        ]);
    }

    public function store(MegaMenuRequest $request): RedirectResponse
    {
        MegaMenu::query()->create($request->validated());

        return to_route('dashboard.admin.mega-menu.index')->with([
            'type'    => 'success',
            'message' => trans('Mega Menu created successfully.'),
        ]);
    }

    public function edit($megaMenu)
    {
        $megaMenu = MegaMenu::query()->findOrFail($megaMenu);

        return view('mega-menu::edit', [
            'item'	  => $megaMenu,
            'title'  => trans('Create Mega Menu'),
            'method' => 'PUT',
            'action' => route('dashboard.admin.mega-menu.update', $megaMenu->id),
        ]);
    }

    public function update(MegaMenuRequest $request, MegaMenu $megaMenu): RedirectResponse
    {
        $megaMenu->update($request->validated());

        return to_route('dashboard.admin.mega-menu.index')->with([
            'type'    => 'success',
            'message' => 'Mega Menu updated successfully.',
        ]);
    }

    public function destroy(MegaMenu $megaMenu): RedirectResponse
    {
        $megaMenu->delete();

        return to_route('dashboard.admin.mega-menu.index')->with([
            'type'    => 'success',
            'message' => 'Mega Menu deleted successfully.',
        ]);
    }
}
