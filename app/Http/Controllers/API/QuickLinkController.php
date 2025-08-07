<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QuickLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuickLinkController extends Controller
{
    public function index(Request $request)
    {
        $links = QuickLink::where('user_id', $request->user()->id)
            ->when($request->has('category'), fn ($query) => $query->byCategory($request->category))
            ->ordered()
            ->get();

        return response()->json($links);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'icon' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Получаем максимальный порядок для автоинкремента
        $maxOrder = QuickLink::where('user_id', $request->user()->id)->max('order') ?? -1;

        $link = QuickLink::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
            'order' => $request->order ?? ($maxOrder + 1),
        ]);

        return response()->json($link, 201);
    }

    public function show(Request $request, QuickLink $quickLink)
    {
        if ($quickLink->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($quickLink);
    }

    public function update(Request $request, QuickLink $quickLink)
    {
        if ($quickLink->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'icon' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $quickLink->update($validator->validated());

        return response()->json($quickLink);
    }

    public function destroy(Request $request, QuickLink $quickLink)
    {
        if ($quickLink->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $quickLink->delete();

        return response()->json(['message' => 'Link deleted successfully']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'links' => 'required|array',
            'links.*.id' => 'required|integer|exists:quick_links,id',
            'links.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->links as $linkData) {
            QuickLink::where('id', $linkData['id'])
                ->where('user_id', $request->user()->id)
                ->update(['order' => $linkData['order']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    public function categories(Request $request)
    {
        $categories = QuickLink::where('user_id', $request->user()->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($categories);
    }
}
