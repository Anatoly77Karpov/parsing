<?
//https://snipp.ru/php/curl#link-imitaciya-brauzera
//https://code.mu/ru/php/book/parsing/anti/cookies/

class CurlSet
{
	public $url;
	public $headers;
	public $curl;

	public function __construct($url)
	{
		$this->url = $url;
		$this->headers = array(
			'cache-control: max-age=0',
			'upgrade-insecure-requests: 1',
			'user-agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Safari/537.36',
			'sec-fetch-user: ?1',
			'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
			'x-compress: null',
			'sec-fetch-site: none',
			'sec-fetch-mode: navigate',
			'accept-encoding: deflate, br',
			'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
		);
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_URL, $this->url);
		curl_setopt($this->curl, CURLOPT_COOKIEFILE, $_SERVER['DOCUMENT_ROOT'] . '/cookie.txt');
		curl_setopt($this->curl, CURLOPT_COOKIEJAR, $_SERVER['DOCUMENT_ROOT'] . '/cookie.txt');
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->curl, CURLOPT_HEADER, true);
	}
}

//$html = curl_exec($this->curl);
//curl_close($this->curl);

//echo $html;