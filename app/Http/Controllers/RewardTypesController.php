<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewardTypeRequest;
use App\Http\Requests\UpdateRewardTypeRequest;
use App\Models\RewardCategory;
use App\Models\RewardType;
use Illuminate\Http\Request;

class RewardTypesController extends Controller
{
    public function index(Request $request)
    {
        $query = RewardType::with('category')->withCount('rewards')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('reward_category_id')) {
            $query->where('reward_category_id', $request->reward_category_id);
        }

        $rewardTypes = $query->paginate(15)->withQueryString();
        $categories  = RewardCategory::active()->orderBy('name')->get();

        return view('reward-types.index', compact('rewardTypes', 'categories'));
    }

    public function store(StoreRewardTypeRequest $request)
    {
        $rewardType = RewardType::create([
            'reward_category_id' => $request->reward_category_id ?: null,
            'name'               => $request->name,
            'description'        => $request->description,
            'default_points'     => $request->default_points,
            'is_active'          => $request->boolean('is_active', true),
            'created_by'         => auth()->id(),
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($rewardType)
            ->inLog('reward_type')
            ->withProperties(['name' => $rewardType->name, 'default_points' => $rewardType->default_points])
            ->log('Tạo loại thưởng: ' . $rewardType->name);

        return redirect()->route('reward-types.index')
            ->with('success', 'Đã tạo loại thưởng "' . $rewardType->name . '" thành công!');
    }

    public function update(UpdateRewardTypeRequest $request, RewardType $rewardType)
    {
        $rewardType->update([
            'reward_category_id' => $request->reward_category_id ?: null,
            'name'               => $request->name,
            'description'        => $request->description,
            'default_points'     => $request->default_points,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($rewardType)
            ->inLog('reward_type')
            ->withProperties(['name' => $rewardType->name, 'default_points' => $rewardType->default_points])
            ->log('Cập nhật loại thưởng: ' . $rewardType->name);

        return redirect()->route('reward-types.index')
            ->with('success', 'Đã cập nhật loại thưởng thành công!');
    }

    public function destroy(RewardType $rewardType)
    {
        if ($rewardType->rewards()->exists()) {
            return back()->with('error', 'Không thể xóa loại thưởng đang có phiếu thưởng liên kết.');
        }

        activity()->causedBy(auth()->user())
            ->performedOn($rewardType)
            ->inLog('reward_type')
            ->withProperties(['name' => $rewardType->name])
            ->log('Xóa loại thưởng: ' . $rewardType->name);

        $rewardType->delete();

        return redirect()->route('reward-types.index')
            ->with('success', 'Đã xóa loại thưởng!');
    }
}
