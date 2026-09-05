<?php

namespace App\Extensions\DiscountManager\System\Http\Controllers;

use App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum;
use App\Extensions\DiscountManager\System\Enums\DurationEnum;
use App\Extensions\DiscountManager\System\Http\Requests\BannerStoreRequest;
use App\Extensions\DiscountManager\System\Http\Requests\DiscountStoreRequest;
use App\Extensions\DiscountManager\System\Models\ConditionalDiscount;
use App\Extensions\DiscountManager\System\Models\Discount;
use App\Extensions\DiscountManager\System\Models\PromoBanner;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\AdminController;
use App\Models\Coupon;
use App\Models\Plan;
use App\Services\Payment\Enums\PaymentGatewayEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Throwable;

class DiscountManagerController extends Controller
{
    // index of discount
    public function index(): View
    {
        $discounts = Discount::query()->with('discountable')->paginate(25);

        return view('discount-manager::index', ['discounts' => $discounts]);
    }

    public function discount(Discount $discount): View
    {
        $plans = Plan::all();
        $gateways = array_map(static function ($value) {
            $definition = PaymentGatewayEnum::tryFrom($value)?->gatewayDefinition();

            return [
                'code'  => $definition['code'],
                'title' => $definition['title'],
            ];
        }, PaymentGatewayEnum::activeGateways());

        // Add offline gateway statically
        // $gateways[] = [
        //    'code'  => 'offline',
        //    'title' => 'Offline',
        // ];

        return view('discount-manager::pages.conditional-discount', [
            'plans'    => $plans,
            'gateways' => $gateways,
            'discount' => $discount?->discountable,
        ]);
    }

    public function saveDiscount(DiscountStoreRequest $request, ?ConditionalDiscount $discount = null): RedirectResponse
    {
        $validated = $request->validated();
        $isCreating = is_null($discount);

        // Handle array fields properly
        if (isset($validated['user_type']) && is_array($validated['user_type'])) {
            $validated['user_type'] = implode(',', $validated['user_type']);
        } else {
            $validated['user_type'] = null;
        }

        if (isset($validated['payment_gateway']) && is_array($validated['payment_gateway'])) {
            $validated['payment_gateway'] = implode(',', $validated['payment_gateway']);
        } else {
            $validated['payment_gateway'] = null;
        }

        if (isset($validated['pricing_plans']) && is_array($validated['pricing_plans'])) {
            $validated['pricing_plans'] = implode(',', $validated['pricing_plans']);
        } else {
            $validated['pricing_plans'] = null;
        }

        // Handle scheduling logic
        if (! isset($validated['scheduled']) || ! $validated['scheduled']) {
            // If not scheduled, remove date fields and set scheduled to false
            $validated['scheduled'] = false;
            $validated['start_date'] = null;
            $validated['end_date'] = null;
        } else {
            // If scheduled, ensure we have the dates
            $validated['scheduled'] = true;
        }

        // Create or update
        if ($isCreating) {
            $newCoupon = new Coupon;
            $newCoupon->name = $validated['title'];
            $newCoupon->discount = $validated['amount'];
            $newCoupon->limit = $validated['total_usage_limit'] ?? -1;
            $newCoupon->is_offer = true;
            $newCoupon->is_offer_fixed_price = false; // ($validated['type'] === DiscountTypeEnum::FIXED->value ? true : false);
            $newCoupon->duration = $validated['duration'] ?? DurationEnum::FIRST_MONTH->value;
            $newCoupon->created_by = auth()->user()->id;
            $newCoupon->code = app(AdminController::class)->generateUniqueCode();

            $newCoupon->save();

            $validated['coupon_id'] = $newCoupon->id;
            $discount = ConditionalDiscount::create($validated);
            $discount->discount()->create();
        } else {
            $discount->update($validated);

            $coupon = $discount->coupon;
            if ($coupon) {
                $coupon->name = $validated['title'];
                $coupon->discount = $validated['amount'];
                $coupon->limit = $validated['total_usage_limit'] ?? -1;
                $coupon->is_offer = true;
                $coupon->is_offer_fixed_price = false; // ($validated['type'] === DiscountTypeEnum::FIXED->value ? true : false);
                $coupon->save();
            }
        }

        return redirect(route('dashboard.admin.discount-manager.index'))->with([
            'message' => $isCreating ? 'Discount created successfully' : 'Discount updated successfully',
            'type'    => 'success',
        ]);
    }

    public function banner(Discount $discount): View
    {
        return view('discount-manager::pages.promo-banner', [
            'bannerInfo' => $discount?->discountable,
        ]);
    }

    public function saveBanner(BannerStoreRequest $request, ?PromoBanner $banner = null): RedirectResponse
    {
        $validated = $request->validated();
        $isCreating = is_null($banner);

        // Ensure only one banner is active
        if (! empty($validated['active'])) {
            PromoBanner::where('active', true)
                ->when(! $isCreating, fn ($query) => $query->where('id', '!=', $banner->id))
                ->update(['active' => false]);
        }

        // Handle checkbox values
        $validated['active'] = (bool) ($validated['active'] ?? false);
        $validated['enable_countdown'] = (bool) ($validated['enable_countdown'] ?? false);

        // Handle file upload
        if ($request->hasFile('icon') && $request->file('icon')?->isValid()) {
            $file = $request->file('icon');
            $fileName = uniqid('', true) . '.' . $file->guessExtension();
            $relativePath = 'upload/banners/';
            $absolutePath = public_path($relativePath);
            if (! File::exists($absolutePath)) {
                File::makeDirectory($absolutePath, 0777, true);
            }
            // Delete old file if it's not the default and its being updated
            if (! $isCreating && $banner->icon && $banner->icon !== 'vendor/discount-manager/images/gift.svg') {
                $oldPath = public_path($banner->icon);
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }
            $file->move($absolutePath, $fileName);
            $validated['icon'] = '/' . $relativePath . $fileName;
        } elseif ($isCreating) {
            $validated['icon'] = 'vendor/banners/images/gift.svg';
        }

        // Create or update
        if ($isCreating) {
            $banner = PromoBanner::create($validated);
            $banner->discount()->create();
        } else {
            $banner->update($validated);
        }

        return redirect(route('dashboard.admin.discount-manager.index'))->with([
            'message' => $isCreating ? 'Banner created successfully' : 'Banner updated successfully',
            'type'    => 'success',
        ]);
    }

    // discount duplicate
    public function discountDuplicate(Request $request): \Illuminate\Http\JsonResponse
    {
        $discountId = $request->get('discount_id');
        $discount = Discount::find($discountId);

        if (! $discount) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Discount not found.',
            ], 404);
        }

        try {
            $original = $discount->discountable;

            if (! $original) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Original discountable entity not found.',
                ], 404);
            }

            $newDiscountable = $original->replicate();

            // If it's a banner, ensure the duplicated one is inactive
            if ($newDiscountable instanceof PromoBanner) {
                $newDiscountable->active = false;
            }

            // If it's a ConditionalDiscount, duplicate associated Coupon as well
            if ($newDiscountable instanceof ConditionalDiscount && $original->coupon) {
                $newCoupon = $original->coupon->replicate();
                $newCoupon->code = app(AdminController::class)->generateUniqueCode();
                $newCoupon->created_by = auth()->id();
                $newCoupon->save();

                $newDiscountable->coupon_id = $newCoupon->id;
            }

            $newDiscountable->save();

            $newDiscountable->discount()->create();

            return response()->json([
                'status'  => 'success',
                'message' => 'Discount duplicated successfully.',
            ]);
        } catch (Throwable $th) {
            logger('Discount Duplicate Error:', [
                'errorMessage' => $th->getMessage(),
                'trace'        => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to duplicate discount. Please try again later.',
            ], 500);
        }
    }

    // discount delete
    public function discountDelete(Request $request): \Illuminate\Http\JsonResponse
    {
        $discountId = $request->get('discount_id');
        $discount = Discount::find($discountId);

        if (! $discount) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Discount not found.',
            ], 404);
        }

        try {
            // If it's a banner with uploaded icon, delete the file
            if ($discount->discountable instanceof PromoBanner) {
                $banner = $discount->discountable;
                if ($banner->icon && $banner->icon !== 'vendor/discount-manager/images/gift.svg' && File::exists(public_path($banner->icon))) {
                    File::delete(public_path($banner->icon));
                }
            }

            $discount->coupon?->delete();
            $discount->discountable?->delete();
            $discount?->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Discount deleted successfully',
            ]);
        } catch (Throwable $th) {
            logger('Discount Delete Error:', [
                'errorMessage' => $th->getMessage(),
                'trace'        => $th->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong. Please refresh the page and try again',
            ], 500);
        }
    }
}
