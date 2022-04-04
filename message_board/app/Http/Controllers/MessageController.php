<?php

namespace App\Http\Controllers;

use App\Repositories\MessageRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    // 查詢全部的留言
    public function getAll()
    {
        return MessageRepository::getAllMessage();
    }

    // 查詢id留言
    public function get($id)
    {
        if (!$message = MessageRepository::getMessage($id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }
        return $message;
    }

    // 新增留言
    public function create(Request $request)
    {
        $user = Auth::user();

        $rules = ['content' => 'required|max:20'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "沒有輸入內容或長度超過20個字元"], 400);
        }

        MessageRepository::createMessage($user->id, $request->content);
        return response()->json(["message" => "新增紀錄成功"], 201);
    }

    // 更新id留言
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$message = MessageRepository::getMessage($id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }

        $rules = ['content' => 'required|max:20'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "沒有輸入內容或長度超過20個字元"], 400);
        }

        if (!MessageRepository::updateMessage($id, $user->id, $request->content, $message['version'])) {
            return response()->json(["message" => "更新留言失敗"], 400);
        }
        return response()->json(["message" => "修改成功"], 200);
    }

    // 按讚id留言
    public function like($id)
    {
        $user = Auth::user();

        if (!MessageRepository::getMessage($id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }

        MessageRepository::likeMessage($id, $user->id);
        return response()->json(["message" => "按讚成功"], 200);
    }

    // 刪除id留言
    public function delete($id)
    {
        $user = Auth::user();

        if (!MessageRepository::deleteMessage($id, $user->id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }
        return response()->json(["message" => "刪除成功,沒有返回任何內容"], 204);
    }
}
