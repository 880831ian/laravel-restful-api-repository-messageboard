<?php

namespace App\Repositories;

use App\Models\Message;
use App\Models\Like;

class MessageRepository
{
    public static function getAllMessage()
    {
        return Message::select(
            'message.id',
            'message.user_id',
            "users.username as name",
            'message.version',
            'message.created_at',
            'message.updated_at'
        )
            ->leftjoin('like', 'message.id', '=', 'like.message_id')
            ->leftjoin('users', 'message.user_id', '=', 'users.id')
            ->selectRaw('count(like.id) as like_count')
            ->groupBy('id')
            ->get()
            ->toArray();
    }

    public static function getMessage($id)
    {
        return Message::select(
            'message.id',
            'message.user_id',
            "users.username as name",
            'message.version',
            'message.created_at',
            'message.updated_at'
        )
            ->leftjoin('like', 'message.id', '=', 'like.message_id')
            ->leftjoin('users', 'message.user_id', '=', 'users.id')
            ->selectRaw('count(like.id) as like_count')
            ->groupBy('id')
            ->get()
            ->where('id', $id)
            ->toArray();
    }

    public static function createMessage($id, $content)
    {
        Message::create([
            'user_id' => $id,
            'content' => $content
        ]);
    }

    public static function updateMessage($id, $user_id, $content, $version)
    {
        return Message::where('version', $version)
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->update([
                'content' => $content,
                'version' => $version + 1
            ]);
    }

    public static function likeMessage($id, $user_id)
    {
        Like::create([
            'message_id' => $id,
            'user_id' => $user_id,
            'created_at' => \Carbon\Carbon::now()
        ]);
    }

    public static function deleteMessage($id, $user_id)
    {
        return Message::where('id', $id)
            ->where('user_id', $user_id)
            ->delete();
    }
}
