<?php

namespace App\Extensions\ProductPhotography\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\ProductPhotography\System\Http\Requests\ProductPhotographyRequest;
use App\Extensions\ProductPhotography\System\Models\UserPebblely;
use App\Extensions\ProductPhotography\System\Services\PebblelyService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Usage;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProductPhotographyController extends Controller
{
    public function __construct(
        public PebblelyService $service
    ) {}

    public function index(): View
    {
        return view('product-photography::index', [
            'last'   => UserPebblely::query()->where('user_id', Auth::id())->latest()->first(),
            'themes' => $this->service->getThemes(),
            'images' => UserPebblely::query()->where('user_id', Auth::id())->latest()->get(),
        ]);
    }

    public function store(ProductPhotographyRequest $request): JsonResponse|RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()->back()->with([
                'type'    => 'error',
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $request->validated();

        $driver = Entity::driver(EntityEnum::PEBBLELY)->inputImageCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return redirect()->back()->with([
                'message' => $e->getMessage(),
                'type'    => 'error',
            ]);
        }

        $removedImage = $this->service->removeBg($request->file('image'));

        $response = $this->service->createBg($removedImage, $request->get('background'));

        if (! isset($response['error'])) {

            UserPebblely::query()->create([
                'user_id' => Auth::id(),
                'image'   => $response,
            ]);

            Usage::getSingle()->updateImageCounts($driver->calculate());
            $driver->decreaseCredit();

            return redirect()->back()->with([
                'message' => __('Image Created Successfully'),
                'type'    => 'success',
            ]);
        }

        $errorMsg = str_replace(["\r", "\n"], '', $response['error']);
        $fullMsg = $errorMsg === 'An unexpected error occurred' ? $errorMsg . ' perhaps UserPebblely API Key empty or invalid' : $errorMsg;

        return redirect()->back()->with([
            'message' => $fullMsg,
            'type'    => 'error',
        ]);
    }

    public function delete(string $id): JsonResponse|RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()->back()->with(['message' => trans('This feature is disabled in demo mode.'), 'type' => 'error']);
        }

        $model = UserPebblely::query()->findOrFail($id);

        $model->delete();

        return redirect()->back()->with(['message' => __('Deleted Successfully'), 'type' => 'success']);
    }
}
