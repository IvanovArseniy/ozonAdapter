<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;

class CategoryController extends BaseController
{
    public function getCategoryName(Request $request, $categoryId)
    {
        Log::info('Get category name: ' . $categoryId);
        return response()->json(['name' => config('app.active_category_name')]);
    }

    public function getCategoryList(Request $request)
    {
        Log::info('Get category list.');
        return response()->json([
            'id' => config('app.active_category_id'),
            'name' => config('app.active_category_name')
        ]);
    }

    public function addCategory(Request $request)
    {
        Log::info('Add category: ' . json_encode($request->getContent()));
        return response()->json(['id' => config('app.active_category_id')]);
    }

    public function updateCategoryName(Request $request, $categoryId)
    {
        Log::info('Update category name: ' . $categoryId);
        return response()->json(['name' => config('app.active_category_name')]);
    }

    public function uploadCategories(OzonService $ozonService) {
        $ozonService->insertCategories();
        return response()->json(['Result' => 'Ok']);
    }
}