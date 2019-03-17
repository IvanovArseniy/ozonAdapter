<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;

class CategoryController extends BaseController
{
    public function getCategoryName(OzonService $ozonService, Request $request, $categoryId)
    {
        Log::info('Get category name: ' . $categoryId);
        $category = $ozonService->getCategory($categoryId);
        Log::info('Get category response: ' . json_encode(['name' => $category['name']]));
        if (!is_null($category)) {
            return response()->json(['name' => $category['name']]);
        }
        else {
            return response()->json([
                'Error' => 'Category with Id=' . $categoryId . ' does not exists!'
            ]);
        }
    }

    public function getCategoryList(OzonService $ozonService, Request $request)
    {
        Log::info('Get category list.');
        $categories = $ozonService->getCategoryList();
        Log::info('Get category list response: ' . json_encode(['items' => $categories]));
        if (!is_null($categories)) {
            return response()->json(['items' => $categories]);
        }
        else {
            return response()->json([]);
        }
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