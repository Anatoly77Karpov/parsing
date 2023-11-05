//Парсим полностью старый сайт Димы Трепачева http://dep.code.mu/

<?php

ini_set('max_execution_time', '10000');
set_time_limit(0);
ini_set('memory_limit', '4096M');
ignore_user_abort(true);

/*-----------------------------Класс для работы со страницей сайта--------------------------------*/

class Page
{
    public $link; // внутренняя относительная ссылка, типа /content/cms/wordpress/
    const MAINPAGE = 'http://dep.code.mu';
    const HOMEDIR = '/domains/dep.code.loc';

    public function __construct($link)
    {

        $this->link = $link;
    }

    public function getFile($link)
    {

        //return file_get_contents($this->link);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::MAINPAGE . $link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        /* принимаем и отправляем куки для имитации человека - разобраться как правильно
        $cookieFilePath = $_SERVER['DOCUMENT_ROOT'] . 'cookie.txt';
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFilePath);
        curl_setopt($curl, CURLOPT_COOKIEJAR,  $cookieFilePath);
        */
        // установка юзер-агента в браузере
        //curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)');
        return curl_exec($curl);
    }

    public function getInnerPageLinks()
    {

        // получаем массив ссылок на внутренние страницы, расположенных на текущей странице
        preg_match_all('#<a[^>]+href="(.+?)"#su', $this->getFile($this->link), $urls);
        $innerPageLinks = [];
        foreach ($urls[1] as $url)
        {
            if (!$this->isOutLink($url) and strlen($url) > 1 and $url[0] != '#'){
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

    public function getTitle()
    {

        preg_match('#<h1>(.+?)</h1>#su', $this->getFile($this->link), $title);
        return $title[1];
    }

    public function isOutLink($url)
    {

        return substr($url, 0, 4) == 'http';
    }

    public function isFileLink($link) // является ли ссылкой на файл (или это директория)
    {

        preg_match('#([^/]+)$#su', $link, $tail);
        return isset($tail[1]) ? str_contains($tail[1], '.') : false;
    }

    public function getChainOfDirs($link)
    {

        // определяем цепочку директорий, к-е необходимо создать для размещения текущей страницы
        $chainOfDirs = [];
        if ($this->isFileLink($link)) {
            $link = preg_replace('#[^/]+$#su', '', $link);
        }
        $dirs = array_diff(explode('/', $link), ['']);
        //preg_match_all('#(/[^/]+)#su', $url, $dirs);
        $i = 0;
        foreach($dirs as $dir)
        {
            if(!isset($chainOfDirs[$i-1])) {
                $chainOfDirs[$i] = '/' . $dir . '/';
            } else {
                $chainOfDirs[$i] = $chainOfDirs[$i-1] . $dir . '/';
            }
            $i++;
        }
        return $chainOfDirs;
    }

    public function setDir($link)
    {

        // если это файл, то обрезаем имя файла, оставив ссылку на директорию
        $linkDir = $this->isFileLink($link) ? preg_replace('#[^/]+$#su', '', $link) : $link;
        // создаём цепочку директорий для размещения текущей страницы
        if(!is_dir(self::HOMEDIR . $linkDir)) {
            $dirs = $this->getChainOfDirs($linkDir);
            foreach($dirs as $dir) {
                if(!is_dir(self::HOMEDIR . $dir)) {
                    mkdir(self::HOMEDIR . $dir);
                }
            }
        }
    }

    public function normalizeFileLink($link)
    {

        // обрезаем окончание адреса, типа ?v=23
        $normalizedLink = preg_replace('#(\?v=\d+)#su', '', $link);
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
        return preg_replace('#(\?v=\d+)#su', '', $url);
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
        file_put_contents(self::HOMEDIR . $this->link . 'index.php', $this->normalizePage());
    }

    public function setImages()
    {

        // сохраняем картинки
        $imageLinks = $this->getInnerImageLinks();
        foreach ($imageLinks as $imageLink)
        {
            $file = $this->getFile($imageLink);
            $normalizedImageLink = $this->normalizeFileLink($imageLink);
            $this->setDir($normalizedImageLink);
            if (!is_file(self::HOMEDIR . $normalizedImageLink)) {
                file_put_contents(self::HOMEDIR . $normalizedImageLink, $file);
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
            if (!is_file(self::HOMEDIR . $normalizedScriptLink)) {
                file_put_contents(self::HOMEDIR . $normalizedScriptLink, $script);
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
            if (!is_file(self::HOMEDIR . $normalizedStyleLink)) {
                file_put_contents(self::HOMEDIR . $normalizedStyleLink, $file);
            }
        }
    }
}

/*-----------------------------Класс для работы с сайтом целиком--------------------------------*/

class Site
{
    const MAINPAGE = 'http://dep.code.loc';
    public $allPageLinks = [];

    public function __construct()
    {

        $this->allPageLinks = $this->getAllPageLinks();
        // добавляем в БД главную страницу сайта
        if (!in_array('/', $this->allPageLinks))
        {
            $query = "INSERT INTO dep_code_mu (link, parsed) VALUES ('/', 0)";
            mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
        }
        // добавляем в БД ссылки на внутренние страницы, расположенные на главной странице
        $this->addPageLinks('/');
    }

    public function linkDb()
    {

        $link = mysqli_connect('localhost', 'root', '', 'test');
        mysqli_query($link, "SET NAMES 'utf8'");
        return $link;
    }

    public function count()
    {

        // подсчёт количества не пропаршенных страниц
        $query = "SELECT COUNT(*) as count FROM dep_code_mu WHERE parsed = 0";
        $res = mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
        return mysqli_fetch_assoc($res)['count'];
    }

    public function getAllPageLinks()
    {

        // получаем массив всех ссылок в БД
        $query = "SELECT link FROM dep_code_mu";
        $res = mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
        $num = mysqli_num_rows($res);
        $links = [];
        for ($i=0; $i < $num; $i++)
        {
            $links[] = mysqli_fetch_assoc($res)['link'];
        }
        //for ($links = []; $link = mysqli_fetch_assoc($res)['link']; $links[] = $link);
        return $links;
    }

    public function getNextLink()
    {

        // получение из БД следующей ссылки для парсинга
        $query = "SELECT link FROM dep_code_mu WHERE parsed = 0 LIMIT 1";
        $res = mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
        return mysqli_fetch_assoc($res)['link'];
    }

    public function markLinkParsed($link)
    {

        $query = "UPDATE dep_code_mu SET parsed = 1 WHERE link = '$link'";
        mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
    }

    public function addPageLinks($link)
    {

        // добавляем в БД исходящие ссылки на страницы сайта, расположенные на данной странице
        $page = new Page($link);
        $linksToAdd = array_diff($page->getInnerPageLinks(), $this->allPageLinks);
        foreach ($linksToAdd as $link)
        {
            $query = "INSERT INTO dep_code_mu (link, parsed) VALUES ('$link', 0)";
            mysqli_query($this->linkDb(), $query) or die(mysqli_error($this->linkDb()));
        }
        // обновляем список всех страниц сайта
        $this->allPageLinks = array_merge($this->allPageLinks, $linksToAdd);
    }

    public function parsing()
    {

        $i = 1;
        while ($this->count() > 0)
        {
            $link = $this->getNextLink();
            if ($link) {
                $page = new Page($link);
                $page->setImages();
                $page->setScripts();
                $page->setStyles();
                $page->setPage();
                $this->addPageLinks($link);
                $this->markLinkParsed($link);
                echo $i . '. page ' . '<a href="' . self::MAINPAGE . $link . '" target="_blank">' . $page->getTitle() . '</a> has been parsed' . '<br>';
                $i++;
                //sleep(rand(3, 5));
            } else {
                echo 'All the pages have been parsed!!!';
            }
        }
    }
}

//Запуск скрипта
//$site = (new Site)->parsing();
//$site = new Site;
//echo 'all the links in DB: ' . count($site->getAllPageLinks()) . ' vs unique array: ' . count(array_unique($site->getAllPageLinks())) . '<br>';
//echo 'There are ' . $site->count() . ' pages left to parse';