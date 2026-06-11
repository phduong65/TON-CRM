<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewardCategoryRequest;
use App\Http\Requests\UpdateRewardCategoryRequest;
use App\Models\RewardCategory;
use Illuminate\Http\Request;

class RewardCategoriesController extends Controller
{
    public function index(Request $request)
    {
        $query = RewardCategory::withCount('rewardTypes')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $categories = $query->paginate(15)->withQueryString();

        return view('reward-categories.index', compact('categories'));
    }

    public function store(StoreRewardCategoryRequest $request)
    {
        $category = RewardCategory::create([
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => auth()->id(),
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($category)
            ->inLog('reward_category')
            ->withProperties(['name' => $category->name])
            ->log('Tạo danh mục thưởng: ' . $category->name);

        return redirect()->route('reward-categories.index')
            ->with('success', 'Đã tạo danh mục thưởng "' . $category->name . '" thành công!');
    }

    public function update(UpdateRewardCategoryRequest $request, RewardCategory $rewardCategory)
    {
        $rewardCategory->update([
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($rewardCategory)
            ->inLog('reward_category')
            ->withProperties(['name' => $rewardCategory->name])
            ->log('Cập nhật danh mục thưởng: ' . $rewardCategory->name);

        return redirect()->route('reward-categories.index')
            ->with('success', 'Đã cập nhật danh mục thưởng thành công!');
    }

    public function destroy(RewardCategory $rewardCategory)
    {
        if ($rewardCategory->rewardTypes()->exists()) {
            return back()->with('error', 'Không thể xóa danh mục đang có loại thưởng liên kết.');
        }

        activity()->causedBy(auth()->user())
            ->performedOn($rewardCategory)
            ->inLog('reward_category')
            ->withProperties(['name' => $rewardCategory->name])
            ->log('Xóa danh mục thưởng: ' . $rewardCategory->name);

        $rewardCategory->delete();

        return redirect()->route('reward-categories.index')
            ->with('success', 'Đã xóa danh mục thưởng!');
    }
}
