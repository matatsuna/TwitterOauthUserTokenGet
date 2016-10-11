<?php
/* PHPでTwitter OAuth認証を行うサンプル・プログラム
 * Author   : お出汁
 * Creation : 2010/10/12
 * Update   : 2013/01/21
 * Version  : 0.3
 * Twitter API Wikiのabraham's twitteroauth 0.2.0を使わせて頂いています。
 */
require('twitteroauth/twitteroauth.php');
// アプリの認証用トークン
$consumer_key = 'foo';
$consumer_secret = 'bar';
// ユーザのアクセス・トークン
// BOTなど、すでにユーザが分かっている場合は、そのユーザのトークンを指定する。
// 不特定多数のユーザが使う場合はNULLにしておき、認証により取得する。
$def_user_key = NULL;
$def_user_secret = NULL;
// アプリケーションのアドレス
$app_addr = 'http://127.0.0.1:8080/';
// アプリケーションのタイトル
$app_title = 'baz';
/*
  開発用：環境変数などを表示する
*/
function DebugOut() {
	$html = '';
	// 普段はコメントに。
	/*
	$html .= '<hr><h1>GET</h1><ul>';
	foreach ($_GET as $key => $val) {
		$html .= '<li>' . $key . ' = ' . $val . '</li>';
	}
	$html .= '</ul><h1>POST</h1><ul>';
	foreach ($_POST as $key => $val) {
		$html .= '<li>' . $key . ' = ' . $val . '</li>';
	}
	$html .= '</ul><h1>SESSION</h1><ul>';
	foreach ($_SESSION as $key => $val) {
		$html .= '<li>' . $key . ' = ' . $val . '</li>';
	}
	$html .= '</ul><h1>COOKIE</h1><ul>';
	foreach ($_COOKIE as $key => $val) {
		$html .= '<li>' . $key . ' = ' . $val . '</li>';
	}
	$html .= '</ul>';
	*/
	return $html;
}
/*
	認証ページへのリンクを表示する
*/
function PrintAuthPage($auth_addr) {
	global $app_title;
	
	header('Content-Type: text/html; charset=UTF-8');
	print('<html><head><title>' . $app_title . '</title></head><body>');
	print('<h1>' . $app_title . '</h1>');
	print('<p>You are not authorized. <a href="' . $auth_addr . '">Please click this link</a> to go to Twitter authorization page.</p>');
	
	// デバッグ出力
	print(DebugOut());
	
	print('</body></html>');
}
/*
	アプリのメインページを表示する
*/
function PrintAppPage() {
	global $app_addr, $app_title;
	header('Content-Type: text/html; charset=UTF-8');
	print('<html><head><title>' . $app_title . '</title></head><body>');
	print('<h1>' . $app_title . '</h1>');
	print('<p><a href="' . $app_addr . '?action=logout">Log out</a></p>');
	// 投稿フォーム
	print('<form action="' . $app_addr . '" method="POST">');
	print('<input type="text" name="q" value="">');
	print('<input type="submit" value="Tweet!">');
	print('</form>');
	
	// デバッグ出力
	print(DebugOut());
	
	print('</body></html>');
}
/*
	アプリの実行（TwitterのAPIを起動）
*/
function ExecApp($client) {
	// ここではテキストボックスの内容をTweetしてみる
	if (!empty($_POST['q'])) {
		// NOTE 2013/01/21:
		// 2012年10月よりTwitterのAPIが変更になり、
		// 新しいアドレスを叩く必要があります。
		
		// 旧
		//$ret = $client->oAuthRequest('https://twitter.com/statuses/update.xml', 'POST', array('status' => $_POST['q']));
		
		// 新
		$ret = $client->oAuthRequest('http://api.twitter.com/1/statuses/update.xml', 'POST', array('status' => $_POST['q']));
	}
	// $retにはリクエストに対するXMLリザルトが入る。必要に応じて解析する。
}
/*
	ログアウト処理
*/
function Logout() {
	// セッションに保存していたトークンを全て削除する。
	unset($_SESSION['oauth_token']);
	unset($_SESSION['oauth_token_secret']);
	unset($_SESSION['oauth_state']);
}
/*
	Twitterの認証を行い、認証済みならTwitterOAuthクライアントを返す。
	未認証の場合は認証ページのアドレスを返す。
*/
function Authorize() {
	global $consumer_key, $consumer_secret;
	global $def_user_key, $def_user_secret;
	global $app_addr;
	
	if (!empty($def_user_key) && !empty($def_user_secret)) {
		/* コード側でユーザのアクセス・トークンが直接指定されている場合、
		 * そのトークンを用いてクライアントを作成する。
		 * この場合セッションは使用しない。
		 */
		$client = new TwitterOAuth($consumer_key, $consumer_secret, $def_user_key, $def_user_secret);
		return array('client' => $client);
	}
	
	if ($_SESSION['oauth_state'] == 'done') {
		/* 認証状態が「認証済み」なら、すでにアプリを利用中である。
		 * クライアントをアクセス・トークンで起動して返す。
		 */
		$client = new TwitterOAuth($consumer_key, $consumer_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
		return array('client' => $client);
	}
	
	if ($_SESSION['oauth_state'] == 'wait') {
		/* 認証状態が「認証待ち」なら、ユーザが認証ページから帰ってきた状態である。
		 * ユーザの持って帰ってきた認証コードとリクエスト・トークンからアクセス・トークンを作成し、
		 * ユーザの認証を完了する。
		 */
		
		// Twitterクライアントをリクエスト・トークンで起動
		$client = new TwitterOAuth($consumer_key, $consumer_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
		
		// アクセス・トークンを取得
		$token = $client->getAccessToken($_GET['oauth_verifier']);
		
		if (empty($token['oauth_token'])) {
			/* アクセス・トークンがなければ、何らかの理由で取得失敗した。
			 * もう一度リクエスト・トークンを生成して認証を試みる。
			 */
			$token = $client->getRequestToken($app_addr);
			$_SESSION['oauth_token'] = $token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $token['oauth_token_secret'];
			
			// 認証状態は「認証待ち」のまま。
			
			// 認証ページのアドレスを返す
			return array('auth_addr' => $client->authorizeURL() . '?oauth_token=' . $token['oauth_token']);
		}
		
		/* アクセス・トークンを記憶し、認証状態を「認証済み」にする。
		 * なお、クライアント内部に保存されているトークンは、
		 * この時点でリクエスト・トークンからアクセス・トークンに変わっている。
		 */
		$_SESSION['oauth_token'] = $token['oauth_token'];
		$_SESSION['oauth_token_secret'] = $token['oauth_token_secret'];
		$_SESSION['oauth_state'] = 'done';
		
		/* 認証成功したのでクライアント・オブジェクトを返す。
		 * ただし、認証直後はおそらくアプリ側になにもGET or POSTされていない。
		 * ExecApp()の作り方に注意する。
		 */
		return array('client' => $client);
	}
	
	/* ここまでどのifにも引っかからなければ、、ユーザはまだ認証していない。
	 * リクエスト・トークンを生成してセッションに保存し、
	 * ユーザを認証ページに誘導する（この処理はmain()で行っている）。
	 */
	
	// Twitterクライアント起動
	$client = new TwitterOAuth($consumer_key, $consumer_secret);
	
	// リクエスト・トークンを取得してセッション変数に保存
	// 認証状態を「認証待ち」にする
	$token = $client->getRequestToken($app_addr);
	$_SESSION['oauth_token'] = $token['oauth_token'];
	$_SESSION['oauth_token_secret'] = $token['oauth_token_secret'];
	$_SESSION['oauth_state'] = 'wait';
	
	// 認証ページのアドレスを返す
	return array('auth_addr' => $client->authorizeURL() . '?oauth_token=' . $token['oauth_token']);
}
/*
	エントリ・ポイント
*/
function main() {
	global $def_user_key, $def_user_secret;
	if (empty($def_user_key) || empty($def_user_secret)) {
		// リクエスト・トークンとアクセス・トークンを覚えるために、
		// セッションを起動する
		session_start();
		
		if ($_GET['action'] == 'logout') {
			// ログアウト
			Logout();
		}
	}
	
	// 認証とTwitterOAuthオブジェクト／認証ページのアドレスの取得
	$ret = Authorize();
	if (empty($ret['client'])) {
		// 認証されていなければ、Twitterの認証ページに誘導する。
		PrintAuthPage($ret['auth_addr']);
	} else {
		// 認証していれば、アプリを実行してページを表示する。
		ExecApp($ret['client']);
		PrintAppPage();
	}
}
main();
?>