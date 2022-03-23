
本程式為 [Laravel 進階 (內建會員系統、驗證 RESTful API 是否登入、使用 Repository 設計模式) ](https://pin-yi.me/laravel-advanced) 範例檔案，請先參閱該文章><

#### Migration

在此之前，我們先來修改一下上次的 `migration` {日期時間}_create_message_table.php 檔案吧

```php
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
```
可以看到我們將資料庫的名稱從 `messsages` 改為 `message` ，後續程式部分也都會修改，大家要在注意一下 ～ 

我們這次加入了留言者 ID (使用外鍵連接 `users` 的 `id`)、按讚者 ID (使用外鍵連接 `users` 的 `id`)、留言板樂觀鎖、softDeletes軟刪除的欄位(軟刪除後續會提到)，並且因為我們同樣的資料不要重複儲存，所以刪除 `name` 要查詢就使用 join 來做查詢。

我們還希望可以多一個來存放是誰按讚的的資料表。所以一樣使用 `migration`  新增一個 {日期時間}_create_like_table.php 檔案

```php
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
```
會存放文章的編號並且使用外鍵連接 `message ` 的 `id`，以及按讚者的 ID 也使用外鍵連接 `users ` 的 `id`。


<br>

列出本次會使用的功能以及對應的方法、是否需要登入、登入後其他人是否可以操作
<br>
| 功能 | 方法 | 是否需要登入 | 登入後其他人是否可以操作 | 
| :---: | :---: | :---: | :---: |
| 查詢全部留言 | getAllMessage | 否 | 不需登入 | 
| 查詢{id}留言 | getMessage | 否 | 不需登入 | 
| 新增留言 | createMessage | 是 | 否 |
| 修改{id}留言 | updateMessage | 是 | 否 | 
| 按讚{id}留言 | likeMessage | 是 | 可以 |
| 刪除{id}留言 | deleteMessage | 是 | 否 |

<br>


#### 登入 API 

我們上面介紹有使用到 Laravel 內建的登入 LoginController 來進行登入，但通常我們在使用時，都會另外再多一個登入用的 API ，那我們來看一下要怎麼設計吧！

我們先使用 `php artisan make:controller LoginController` 新增一個登入的 API，他會產生在 `app/Http/Controllers/` 目錄下

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "格式錯誤"], 400);
        }

        if (!Auth::attempt([
            'username' => $request->username,
            'password' => $request->password
        ])) {
            return response()->json(["message" => "登入失敗"], 401);
        }
        return response()->json(["message" => "登入成功"], 200);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(["message" => "登出成功"], 200);
    }
}
```
這邊的 Login 會先驗證格式是否正確，在使用 `Auth:attempt` 來檢查是否有註冊過，並且回傳相對應的訊息， Logout 就使用 `Auth::logout` 即可。

好了後我們先到 routes/api.php 新增登入跟登出 API 的路徑

```php
Route::post('login', 'LoginController@login');
Route::post('logout', 'LoginController@logout');
```


<br>

我們接下來設定 Middleware ，什麼是 Middleware 呢！？

#### Middleware
Middleware 中文翻譯是中介軟體，是指從發出請求 (Request)之後，到接收回應(Response)這段來回的途徑上，

用來處理特定用途的程式，比較常用的 **Middleware** 有身份認證 (Identity) 、路由(Routing) 等，再舉個例子

```
某天早上你去圖書館看書，
下午去公園畫畫，
晚上去KTV 唱歌，
等到要準備回家的時候發現學生證不見了，
你會去哪裡找? (假設學生證就掉在這3個地方)
```

對於記憶不好的人來說，會按照 KTV > 公園 > 圖書館的路線去尋找。

假設在公園找到學生證，就不會再去圖書館了，由於這條路是死巷，所以只能返回走去KTV的路，這個就是 **Middleware** 的運作原理。

所以我們需要再請求時，先檢查是否有登入，才可以去執行需要權限的功能。

我們可以使用內建的 `Auth::check` 來檢查是否有登入，我們接著看要怎麼做吧！

<br>

先下指令生成一個放置登入驗證權限的 `Middleware` ，我把它取名為 `ApiAuth`
```sh
$  php artisan make:middleware ApiAuth
Middleware created successfully.
```

<br>

接著要把剛剛生成的 `ApiAuth` 檔案放置在 `app/Http/Kernel.php` 檔案中

```php
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'api.auth' => \App\Http\Middleware\ApiAuth::class // 這邊
    ];
```

接下來我們就可以開始撰寫 `ApiAuth` 檔案的內容了

```php
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return response(['message' => '用戶需要認證'], 401);
        }
        return $next($request);
    }
```
這邊的意思是指，在 request 的時候，我們使用內建的 `Auth::check` 來檢查，如果登入就可以繼續使用，如果沒有登入會回傳用戶需要認證以及 401 的 status code。

<br>

##### Route

接下來，我們要把我們設定好的 `ApiAuth` 設定在 `route\api.php` 的路由中，
```php
Route::get('message', 'MessageController@getAll');
Route::get('message/{id}', 'MessageController@get');
Route::post('message', 'MessageController@create')->middleware('api.auth');
Route::put('message/{id}', 'MessageController@update')->middleware('api.auth');
Route::patch('message/{id}', 'MessageController@like')->middleware('api.auth');
Route::delete('message/{id}', 'MessageController@delete')->middleware('api.auth');
```
可以看到跟我們上一篇的 route 設定的差不多，只是將 `MessageController` 後面的方法做了一點變化，簡化名稱(這與我們後面講到的 Repository 設計模式有關)，以及加上 `like` 這個方法來當作我們的按讚功能，後面將我們需要登入驗證才可以使用的功能，加入 `middleware('api.auth');`

<br>

##### Model

因為我們在取得資料時，不希望顯示 `deleted_at` 給使用者，所以在 `app\Models\Message.php` 這個 model 裡面用 `hidden` 來做設定。

**message**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'Message';
    protected $fillable = [
        'user_id', 'name', 'content'
    ];
    protected $hidden = [
        'deleted_at'
    ];
}
```
可以看到我們還多引用 SoftDeletes ，它叫軟刪除，一般來說我們在設計資料庫時，不會真正意義上的把資料刪掉，還記得我們再新增 message 跟 like 資料表時，有多了一個  `softDeletes()` 欄位嗎，這個欄位就是當我們刪除時，他會記錄刪除時間，但在查詢時就不會顯示這筆資料，讓使用者覺得資料已經真正刪除了，但實際上資料還是存在在資料庫中。


<br>

**like**

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Like extends Model
{
    use SoftDeletes;

    protected $table = 'like';
    protected $fillable = [
        'message_id', 'user_id', 'created_at'
    ];
    public $timestamps = false;
}
```

可以看到我們還多引用 SoftDeletes ，它叫軟刪除，一般來說我們在設計資料庫時，不會真正意義上的把資料刪掉，還記得我們再新增 message 跟 like 資料表時，有多了一個  `softDeletes()` 欄位嗎，這個欄位就是當我們刪除時，他會記錄刪除時間，但在查詢時就不會顯示這筆資料，讓使用者覺得資料已經真正刪除了，但實際上資料還是存在在資料庫中。


<br>

##### Repository

還記得我們上次把 RESTful API 要處理的邏輯都寫在 Controller 裡面嗎，我們光是一個小功能就讓整個 Controller 變得肥大，在後續維護時或是新增功能時，會導致十分不便利，因此我們要將 Repository 設計模式 給導入，那要怎麼實作呢～

<br>

我們要先在 `app` 底下新增一個 `Repositories` 目錄，在目錄底下再新增一個 `MessageRepository.php` 檔案來專門放我們與資料庫拿資料的程式，讓 Controller 單純處理商業邏輯我們整個檔案分成幾段來看

<br>

先新增這個檔案的命名空間，並將我們原先放在 `MessageController` 的引用給拿進來，我們 Repositories 單純處理與資料庫的交握，或是引用 Message 跟 like 的 Models。
```php
namespace App\Repositories;

use App\Models\Message;
use App\Models\Like;
```

<br>

**查詢留言資料讀取**
```php
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
```
回傳全部的留言資料 `getAllMessage()`，由於我們想要顯示留言者 id，只能一個一個把我們想要的 select 出來，不能透過 model 來顯示，使用 leftjoin 來查詢，最後多一個來顯示各個文章的總數。

回傳{id}留言資料 `getMessage($id)`，一樣跟回傳全部的留言資料一樣，只是多一個 where 來顯示輸出的 id 留言資料。

<br>

**新增留言資料讀取**

```php
    public static function createMessage($id, $content)
    {
        Message::create([
            'user_id' => $id,
            'content' => $content
        ]);
    }
```
使用 create 來新增資料，將 user_id 帶入傳值進來的 `$id`，content 帶入 `$content`。

<br>

**修改留言資料讀取**

```php
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
```
先使用 where 來檢查樂觀鎖 version ，在查詢此 id 是否存在，以及編輯者是否為發文者，最後用 update 來更新資料表，分別更新 user_id、content、version(樂觀鎖每次加1) 等欄位。

<br>

**按讚留言資料讀取**

```php
    public static function likeMessage($id, $user_id)
    {
        Like::create([
            'message_id' => $id,
            'user_id' => $user_id,
            'created_at' => \Carbon\Carbon::now()
        ]);
    }
```
按讚我們會在 like 的資料表中來新增紀錄，所以也是使用 create 來新增，新增 message_id、user_id、created_at 。

<br>

**刪除留言資料讀取**

```php
    public static function deleteMessage($id, $user_id)
    {
        return Message::where('id', $id)
            ->where('user_id', $user_id)
            ->delete();
    }
```
刪除功能因為我們使用軟刪除，所以不用顧慮 FK 外鍵，所以可以直接刪除 message。

<br>


##### Controller

到這裡我們講完 `MessageRepository.php` 的內容了，那原本的 Controller 剩下什麼呢 !?

```php
namespace App\Http\Controllers;

use App\Repositories\MessageRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
```
我們在商業邏輯上會處理 Request 內容，以及使用 MessageRepository 來對資料庫做存取、Auth 取的登入者的資訊等等功能，所以要記得先把他引用進來歐。

<br>

**查詢留言功能**


```php
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
``` 

<font color='red'>由於我們 MessageRepository 都只有單純與資料庫進行交握，所有的判斷以及回傳都會在 controller 來做處理</font>，`getAll()` 會使用到 `MessageRepository::getAllMessage()` 的查詢並回傳顯示查詢的資料。

`get(id)` 會先用 MessageRepository::getMessage($id) 來檢查 id 是否存在，如果不存在就會回傳找不到留言 404 的 status code，如果存在就回傳存在變數 `message` 的 `MessageRepository::getMessage($id)` 資料。

<br>


**新增留言功能**

```php
    // 新增留言
    public function create(Request $request)
    {
        $user = Auth::user();

        $rules = ['content' => 'max:20'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "內容長度超過20個字元"], 400);
        }

        MessageRepository::createMessage($user->id, $request->content);
        return response()->json(["message" => "新增紀錄成功"], 201);
    }
```

我們先使用 `Auth::user()` 將登入的使用者資料存在 `$user` 中，在檢查輸入的 request 內容是否有超過 20 的字元，如果有就回傳內容長度超過20個字元 400。接著就用 MessageRepository::createMessage 將要新增的資料帶入，最後回傳新增紀錄成功 201 。

<br>

**修改留言功能**

```php
    // 更新id留言
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$message = MessageRepository::getMessage($id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }

        $rules = ['content' => 'max:20'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "內容長度超過20個字元"], 400);
        }

        foreach ($message as $key => $value) {
            $user_id = $value['user_id'];
            $version = $value['version'];
        }

        if ($user_id != $user->id) {
            return response()->json(["message" => "權限不正確"], 403);
        }

        MessageRepository::updateMessage($id, $user->id, $request->content, $version);
        return response()->json(["message" => "修改成功"], 200);
    }
```

一樣先把登入的使用者存入 `$user`，檢查是否有這個 id ，沒有就回傳找不到留言 404，接下來檢查輸入的內容長度，如果超過，就回傳內容長度超過20個字元 400，再檢查要修改留言的與留言者是不是同一個使用者，如果不是就回傳權限不正確 403，最後就將資料透過 `MessageRepository::updateMessage` 來做更新，並回傳修改成功 200。

<br>

**按讚留言功能**

```php
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
```
一樣先把登入的使用者存入 `$user`，先檢查是否有這個留言，沒有就回傳找不到留言 404，接著就使用 `MessageRepository::likeMessage` 來記錄按讚留言，並回傳按讚成功 404。

<br>

**刪除留言功能**

```php
    // 刪除id留言
    public function delete($id)
    {
        $user = Auth::user();

        if (!$message = MessageRepository::getMessage($id)) {
            return response()->json(["message" => "找不到留言"], 404);
        }

        foreach ($message as $key => $value) {
            $user_id = $value['user_id'];
        }

        if ($user_id != $user->id) {
            return response()->json(["message" => "權限不正確"], 403);
        }

        MessageRepository::deleteMessage($id, $user->id);
        return response()->json(["message" => "刪除成功,沒有返回任何內容"], 204);
    }
```

一樣先把登入的使用者存入 `$user`，先檢查是否有這個留言，沒有就回傳找不到留言 404，在檢查文章權限，錯誤就回傳權限不正確 403，最後檢查是否有被按讚，有的話要先刪除 like 裡面的按讚紀錄，最後再刪除文章(所有的刪除，因為我們在 model 裡面有使用 softdelete 軟刪除，所以不會真的刪除，而是在欄位的 deleted_at 加入刪除時間，來讓查詢時以為他被刪除了！)

<br>

### Postman 測試

那我們一樣來看一下 Postman 的測試，這邊只顯示需要登入才能使用的 API。

##### 登入

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/login.png.png)

<br> 

我們把帳號密碼放到 Body 來傳送，如果帳號密碼正確，就會顯示登入成功，並且在 Cookie 裡面的 laravel_session，可以用來判斷是否登入，以及登入的人是誰。

<br> 

##### 新增留言 - 成功

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/post-api-success.png)

<br> 

新增留言成功，因為 **<font color='blue'>有登入，所以可以從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** 會顯示新增紀錄成功以及回應 201 Created

<br>

##### 新增留言 - 失敗

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/post-api-error.png)

<br> 

新增留言失敗，因為 **<font color='red'>沒有登入，無法從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，所以會顯示用戶需要認證以及回應 401 Unauthorized

<br>

##### 修改留言 - 成功

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/put-api-success.png)

<br> 

修改留言成功，因為 **<font color='blue'>有登入，所以可以從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，會顯示修改成功以及回應 200 OK

<br>

##### 修改留言 - 失敗 - 沒有登入

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/put-api-error-1.png)

<br> 

修改留言失敗，因為 **<font color='red'>沒有登入，無法從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，會顯示用戶需要認證以及回應 401 Unauthorized

<br>

##### 修改留言 - 失敗 - 權限不足

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/put-api-error-2.png)

<br> 

修改留言失敗，雖然 **<font color='red'>有登入，但存在 Cookie 裡面的 laravel_session 不是當初的留言者</font>** ，會顯示權限不正確以及回應 403 Forbidden

<br>

##### 按讚留言 - 成功

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/patch-api-success.png)

<br> 

按讚留言成功，因為 **<font color='blue'>有登入，所以可以從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，會顯示按讚成功以及回應 200 OK

<br>

##### 按讚留言 - 失敗 - 沒有登入

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/patch-api-error.png)

<br> 

按讚留言失敗，因為 **<font color='red'>沒有登入，無法從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，會顯示用戶需要認證以及回應 401 Unauthorized

<br>

##### 刪除留言 - 成功

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/delete-api-success.png)

<br> 

刪除留言成功，因為 **<font color='blue'>有登入，所以可以從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，不會顯示訊息但會回應 204 No Content

<br>

##### 刪除留言 - 失敗 - 沒有登入

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/delete-api-error-1.png)

<br> 

刪除留言失敗，因為 **<font color='red'>沒有登入，無法從 Cookie 裡面的 laravel_session 來驗證是否登入</font>** ，會顯示用戶需要認證以及回應 401 Unauthorized

<br>

##### 刪除留言 - 失敗 - 權限不足

![圖片](https://raw.githubusercontent.com/880831ian/laravel-restful-api-messageboard/master/images/delete-api-error-2.png)


<br> 

刪除留言失敗，雖然 **<font color='red'>有輸入正確的 token ，但不是當初的留言者</font>** ，會顯示權限不正確以及回應 403 Forbidden
