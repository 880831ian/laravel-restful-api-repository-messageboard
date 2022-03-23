<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message', function (Blueprint $table) {
            $table->increments('id'); //留言板編號
            $table->integer('user_id')->unsigned(); //留言者ID
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('content', 20); //留言板內容
            $table->integer('version')->default(0);
            $table->timestamps(); //留言板建立以及編輯的時間
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
        Schema::dropIfExists('message');
    }
}
