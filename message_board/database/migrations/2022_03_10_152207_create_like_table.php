<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLikeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('like', function (Blueprint $table) {
            $table->bigIncrements('id'); //按讚紀錄編號
            $table->integer('message_id')->unsigned()->nullable(); //文章編號
            $table->foreign('message_id')->references('id')->on('message');
            $table->integer('user_id')->unsigned()->nullable(); //帳號編號
            $table->foreign('user_id')->references('id')->on('users');
            $table->dateTime('created_at'); //按讚紀錄建立時間
            $table->softDeletes(); //軟刪除時間
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('like');
    }
}
