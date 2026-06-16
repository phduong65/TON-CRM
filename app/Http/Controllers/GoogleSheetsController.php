<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Throwable;

class GoogleSheetsController extends Controller
{
    public function index()
    {
        $sheetId = config('google.sheet_id');
        $credPath = config('google.service_account_json_path');
        $credExists = file_exists(base_path($credPath));

        return view('google-sheets.index', compact('sheetId', 'credPath', 'credExists'));
    }

    public function push()
    {
        try {
            $service = new GoogleSheetsService();
            $stats   = $service->pushAll();

            $msg = "Đã đẩy dữ liệu lên Google Sheet: "
                 . "Quy chế ({$stats['regulations']} dòng), "
                 . "Vi phạm ({$stats['violations']} dòng), "
                 . "Danh mục thưởng ({$stats['reward_cats']} dòng), "
                 . "Loại thưởng ({$stats['reward_types']} dòng).";

            return redirect()->route('google-sheets.index')->with('success', $msg);
        } catch (Throwable $e) {
            return redirect()->route('google-sheets.index')
                ->with('error', 'Lỗi khi đẩy dữ liệu: ' . $e->getMessage());
        }
    }

    public function import()
    {
        try {
            $service = new GoogleSheetsService();
            $stats   = $service->importAll();

            $msg = sprintf(
                'Import thành công — Quy chế: +%d cập nhật %d | Vi phạm: +%d cập nhật %d | Danh mục thưởng: +%d cập nhật %d | Loại thưởng: +%d cập nhật %d',
                $stats['regulations']['created'],  $stats['regulations']['updated'],
                $stats['violations']['created'],   $stats['violations']['updated'],
                $stats['reward_cats']['created'],  $stats['reward_cats']['updated'],
                $stats['reward_types']['created'], $stats['reward_types']['updated'],
            );

            return redirect()->route('google-sheets.index')->with('success', $msg);
        } catch (Throwable $e) {
            return redirect()->route('google-sheets.index')
                ->with('error', 'Lỗi khi import: ' . $e->getMessage());
        }
    }
}
