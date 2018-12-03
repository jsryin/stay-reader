<?php
/**
 * User: Chester
 */

namespace App\Crawler;


use Illuminate\Support\Facades\DB;

class StayReader
{
    protected $txtArticleUrl = 'https://www.booktxt.net/modules/article/txtarticle.php';
    protected $articleUrl = "https://www.booktxt.net/";

    protected $downloader = null;
    protected $chapters = [];

    /**
     * StayReader constructor.
     */
    public function __construct()
    {
        $this->downloader = new Downloader();
    }

    /**
     * @param string $id
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function downloadDotBookById($id = '')
    {
        $summary = $this->downloader->downloadBookSummary($this->articleUrl, $id);
        if (!$summary) {
            abort('404', '图书不存在');
        }
        $book = $this->insertBookSummary($summary);
        if ($book === false) {
            abort('500', '图书插入失败');
        }
        $fileName = $this->downloader->downloadTextFile($this->txtArticleUrl, ['id' => $id]);
        $this->parser($fileName, $id);

    }

    public function parser($fileName, $book_id)
    {
        $bookContents = [];
        if (file_exists($fileName)) {
            $handle = fopen($fileName, 'r');
            $spaceNum = 0;
            $firstLine = true;
            $firstChapter = true;

            $chapterName = '';
            $chapterContents = '';
            while (true) {
                if (feof($handle)) {
                    $chapter['book_id'] = $book_id;
                    $chapter['chapter'] = $chapterName;
                    $chapter['contents'] = $chapterContents;
                    $this->insertBookChapter($chapter);
                    break;
                }
                $lineContent = fgets($handle);
                if (trim($lineContent) == '' || $firstLine) {
                    $spaceNum++;
                } else {
                    if ($spaceNum == 3) {
                        $chapter['book_id'] = $book_id;
                        $chapter['chapter'] = $chapterName;
                        $chapter['contents'] = $chapterContents;
                        $firstChapter || $this->insertBookChapter($chapter);
                        $firstChapter = false;
                        $chapterName = $lineContent;
                    } else if ($spaceNum == 4) {
                        $chapterContents = '';
                    } else {
                        $chapterContents = $chapterContents . "{$lineContent}";
                    }
                    $spaceNum = 0;
                }
                $firstLine = false;
            }
            fclose($handle);
        }
        return $bookContents;
    }

    /**
     * @param $chapter
     * @return mixed
     */
    protected function insertBookChapter($chapter)
    {
        $chapter['created_date'] = date("Y-m-d H:i:s");
        $chapter['modified_date'] = date("Y-m-d H:i:s");
        return DB::table('sr_book_contents')->insert($chapter);
    }

    /**
     * @param $book
     * @return mixed
     */
    protected function insertBookSummary($book)
    {
        return DB::table('sr_book')->insert($book);
    }
}