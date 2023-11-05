<?
//Класс для управления парсингом сайта методом паука -
//сбор всех внутренних страниц в массив, расположенный в БД
//с отметками пропаршена страница или нет (0/1)

// Инструкции настройки в файле, который запускается для парсинга
//ini_set('max_execution_time', '10000');
//set_time_limit(0);
//ini_set('memory_limit', '4096M');
//ignore_user_abort(true);

require_once 'page.php';

class Site
{
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
				echo $i . '. page ' . $link . ' with title: ' . $page->getTitle() . ' has been parsed' . '<br>';
				$i++;
				//sleep(rand(3, 5));
			} else {
				echo 'All the pages have been parsed!!!';
			}
		}
	}
}