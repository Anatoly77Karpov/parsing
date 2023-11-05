<?
// Класс для работы со страницей сайта, который нужно спарсить

// Инструкции настройки в файле, который запускается для парсинга
//ini_set('max_execution_time', '10000');
//set_time_limit(0);
//ini_set('memory_limit', '4096M');
//ignore_user_abort(true);

require_once 'curlset.php';

class Page
{
	//внутренняя относительная ссылка, типа /content/cms/wordpress/
	public $link;
	//ссылка на главную страницу сайта, который нужно спарсить, без слэша в конце
	public $mainpage;
	public $charset;
	//абсолютная ссылка на директорию, в которой будет размещаться спарсенный сайт, без слэша в конце
	public $homedir;

	public function __construct($link, $mainpage, $charset = 'utf-8', $homedir = '')
	{
		$this->link = $link;
		$this->mainpage = $mainpage;
		$this->charset = $charset;
		$this->homedir = $homedir;
	}

	public function getTitle()
	{
		preg_match('#<h1>(.+?)</h1>#su', $this->getFile($this->link), $title);
		return $title[1];
	}

	public function isOutLink($url)
	{
		return substr($url, 0, 4) == 'http';
	}

	public function isFileLink($link)
	{
		// является ли ссылкой на файл (или это директория)
		preg_match('#([^/]+)$#su', $link, $tail);
		return isset($tail[1]) ? str_contains($tail[1], '.') : false;
	}

	public function getFile($link)
	{
		$curl = (new CurlSet($this->mainpage . $link))->curl;
		$file = curl_exec($curl);
		if ($this->charset != 'utf-8') {
			return iconv($this->charset, 'utf-8', $file);
		} else {
			return $file;
		}
	}

	public function isValideLink($link)
	{
		//является ли ссылкой на внутреннюю страницу
		if (substr($link, 0, 7) == 'mailto:') {
			return false;
		} elseif ($link[0] == '#' or $link[0] == '"') {
			return false;
		} elseif (substr($link, 0, 4) == 'http') {
			return false;
		} else {
			return true;
		}
	}

	public function getInnerPageLinks()
	{
		// получаем массив ссылок на внутренние страницы, расположенных на текущей странице
		preg_match_all('#<a[^>]+href="(.+?)"#su', $this->getFile($this->link), $urls);
		$innerPageLinks = [];
		foreach ($urls[1] as $url)
		{
			if ($this->isValideLink($url)){
				$innerPageLinks[] = $url;
			}
		}
		return array_unique($innerPageLinks);
	}

	public function getInnerImageLinks()
	{
		// получаем массив ссылок на картинки данной страницы
		preg_match_all('#<img[^>]+src="(.+?)"#su', $this->getFile($this->link), $urls);
		$innerImageLinks = [];
		foreach ($urls[1] as $url)
		{
			if (!$this->isOutLink($url) and $url[0] == '/'){
				$innerImageLinks[] = $url;
			}
		}
		return array_unique($innerImageLinks);
	}

	public function getInnerScriptLinks()
	{
		// получаем массив ссылок на внутренние страницы со скриптами
		preg_match_all('#<script[^>]+src="(.+?)"#su', $this->getFile($this->link), $urls);
		$innerScriptLinks = [];
		foreach ($urls[1] as $url)
		{
			if (!$this->isOutLink($url) and $url[0] == '/'){
				$innerScriptLinks[] = $url;
			}
		}
		return array_unique($innerScriptLinks);
	}

	public function getInnerStyleLinks()
	{
		// получаем массив ссылок на внутренние страницы с таблицами стилей
		preg_match_all('#<link[^>]+href="(.+?)"#su', $this->getFile($this->link), $urls);
		$innerStyleLinks = [];
		foreach ($urls[1] as $url)
		{
			if (!$this->isOutLink($url) and $url[0] == '/'){
				$innerStyleLinks[] = $url;
			}
		}
		return array_unique($innerStyleLinks);
	}

	public function setDir($link)
	{
		//создаём директорию для последующего размещения файлов (index.php, картинок, стилей)
		//если задана ссылка на файл, то обрезаем окончание, оставив ссылку на директорию
		$linkDir = $this->isFileLink($link) ? preg_replace('#[^/]+$#su', '', $link) : $link;
		if (!mkdir($this->homedir . $linkDir, 0777, true)) {
			die ('Ошибка: директория ' . $this->homedir . $linkDir . ' не была создана!');
		}
	}

	public function normalizeFileLink($link)
	{
		// обрезаем окончание адреса, типа ?v=23
		$normalizedLink = $this->cutVersionInUrl($link);
		// очищаем адрес от конструкции /&/, если это картинка
		if (str_contains($normalizedLink, '/&')) {
			preg_match('#(.+)/&(.+)#su', $normalizedLink, $match);
			$normalizedLink = $match[1] . $match[2];
		}
		return $normalizedLink;
	}

	public function cutVersionInUrl($url)
	{
		// обрезаем окончание адреса, типа ?v=23
		return preg_replace('#\?.+$#su', '', $url);
	}

	public function normalizeImageUrl($url)
	{
		// очищаем адрес картинки от конструкции /&/
		// определяем отдельно часть строки до /& и после
		preg_match('#(.+)/&(.+)#su', $url, $match);
		return [$match[1], $match[2], $match[1] . $match[2]];
	}

	public function normalizePage()
	{
		// очищаем адреса картинок, скриптов и стилей на данной странице
		$normalizedPage = $this->getFile($this->link);
		foreach ($this->getInnerImageLinks() as $link)
		{
			$normalizedLink = $this->cutVersionInUrl($link);
			$partBefore = $this->normalizeImageUrl($normalizedLink)[0];
			$partAfter = $this->normalizeImageUrl($normalizedLink)[1];
			$normalizedLink = $this->normalizeImageUrl($normalizedLink)[2];
			$regExp = $partBefore . '/&' . $partAfter . '.*?"';
			$normalizedPage = preg_replace('#'.$regExp.'#su', $normalizedLink . '"', $normalizedPage);
		}
		foreach ($this->getInnerStyleLinks() as $link)
		{
			$normalizedLink = $this->cutVersionInUrl($link);
			$regExp = $normalizedLink . '.*?"';
			$normalizedPage = preg_replace('#'.$regExp.'#su', $normalizedLink . '"', $normalizedPage);
		}
		foreach ($this->getInnerScriptLinks() as $link)
		{
			$normalizedLink = $this->cutVersionInUrl($link);
			$regExp = $normalizedLink . '.*?"';
			$normalizedPage = preg_replace('#'.$regExp.'#su', $normalizedLink . '"', $normalizedPage);
		}
		return $normalizedPage;
	}

	public function setPage()
	{
		// размещаем очищенную спаршенную страницу
		$this->setDir($this->link);
		file_put_contents($this->homedir . $this->link . 'index.php', $this->normalizePage());
	}

	public function setImages()
	{
		// сохраняем картинки
		$imageLinks = $this->getInnerImageLinks();
		foreach ($imageLinks as $imageLink)
		{
			$file = file_get_contents($this->mainpage . $imageLink);
			$normalizedImageLink = $this->normalizeFileLink($imageLink);
			$this->setDir($normalizedImageLink);
			if (!is_file($this->homedir . $normalizedImageLink)) {
				file_put_contents($this->homedir . $normalizedImageLink, $file);
			}
		}
	}

	public function setScripts()
	{
		// сохраняем файлы со скриптами
		$scriptLinks = $this->getInnerScriptLinks();
		foreach ($scriptLinks as $scriptLink)
		{
			$script = $this->getFile($scriptLink);
			$normalizedScriptLink = $this->normalizeFileLink($scriptLink);
			$this->setDir($normalizedScriptLink);
			if (!is_file($this->homedir . $normalizedScriptLink)) {
				file_put_contents($this->homedir . $normalizedScriptLink, $script);
			}
		}
	}

	public function setStyles()
	{
		// сохраняем файлы со стилями
		$styleLinks = $this->getInnerStyleLinks();
		foreach ($styleLinks as $styleLink)
		{
			$file = $this->getFile($styleLink);
			$normalizedStyleLink = $this->normalizeFileLink($styleLink);
			$this->setDir($normalizedStyleLink);
			if (!is_file($this->homedir . $normalizedStyleLink)) {
				file_put_contents($this->homedir . $normalizedStyleLink, $file);
			}
		}
	}
}