<?php
/* article.csv contents: article_id, title, summary (maps to post_excerpts?), category(ID?),
           content, members_only, authorised, last_updated_on, files_present(bool)

    article_cmt.csv: comment_id, article_id, date, author_id, author_name, 
	             email, comment, authorized, IP
 */
 
include_once ("tt_lib.php");

/* Check if article has files attached to it */
function HasFilesUploaded($fd, $id)  {
    $qry = "SELECT a.articles_uploaded_files_name 
            FROM articles_uploaded_files a 
            WHERE a.articles_id=$id";

    $qry_result = mysql_query($qry, $fd);
    if ($qry_result && mysql_fetch_array ($qry_result, MYSQL_ASSOC))  {
        return 1;
    }

    return 0;
}



/* Fetch comments associated with article, if any  */
function ArticleComments($fd, $id)  {
	$qry = 
        "SELECT c.article_comments_id, c.article_comments_article_id, 
            c.article_comments_date, c.article_comments_author_id,
            c.article_comments_author_name,
            c.article_comments_author_email,
            c.article_comments_comment,
            c.article_comments_authorized,
            c.article_comments_ip
        FROM
            article_comments c
        WHERE
            c.article_comments_article_id=$id";

    return mysql_query($qry, $fd);
}


function GetCategoryName($cat_id, $fd)  {
    $qry = "SELECT c.article_categories_name FROM article_categories c WHERE c.article_categories_id=$cat_id";
    $qry_result = mysql_query($qry, $fd);
    if (!$qry_result)  {
        printf("Get category name Query failed: %s", mysql_error());
        return null;
    }
    else  {
        $cname = mysql_fetch_array ($qry_result, MYSQL_ASSOC); 
        return $cname['article_categories_name'];
    }
}


function OpenCSVFile ($name, $cnt)  {
    $fname = $name . "$cnt" . ".csv";
    $fd  = fopen($fname, 'w');
    if (! $fd)  {
        printf ("Error opening file: %s\n", $fname);
        die();
    }

    printf ("Created file: %s\n", $fname);

    return $fd;
}


function WriteCSVHeaders($fd_art, $fd_artcmt)  {
    // Write the CSV header.
    fputcsv($fd_art,split(',',"articles_id,articles_title,articles_summary,articles_category,articles_content,articles_members_only,articles_authorised,articles_last_updated_on,files_present"));
    fputcsv($fd_artcmt,split(',', "comment_id,article_id,date,author_id,author_name,email,comment,authorized,IP"));
}



/* Open the articles CSV for writing */
$fd_artcsv = fopen('csv/article.csv', 'w');
$fd_artcmtcsv = fopen('csv/article_cmt.csv', 'w');

if (!$fd_artcsv  ||  !$fd_artcmtcsv) {
    print("Error opening file\n");
    die();
}

WriteCSVHeaders($fd_artcsv, $fd_artcmtcsv);

$fd = tt_connect('localhost', 'sc', 'calvin', 'tibettimes_old');
$article_entry_qry = 
    "SELECT a.articles_id, a.articles_title, 
            a.articles_summary, a.articles_category, a.articles_content, 
            a.articles_members_only, a.articles_authorised, 
            a.articles_last_updated_on 
    FROM articles a";
       
$artqry_result = mysql_query($article_entry_qry, $fd);
if (!$artqry_result) {
    printf("QUERY FAILED: %s", mysql_error());
}

/* DEBUG
$article = mysql_fetch_array($qry_result, MYSQL_ASSOC);
var_dump($article);
*/
 
$art_entry = array(); $artCnt = 0; $cmtCnt = 0;
while (($art_entry = mysql_fetch_array($artqry_result, MYSQL_ASSOC))) {
    $artCnt++;
    $file_uploaded_flg = HasFilesUploaded($fd, $art_entry['articles_id']);
    $category_name = GetCategoryName($art_entry['articles_category'], $fd);

    fputcsv($fd_artcsv,
         array(
            $art_entry['articles_id'], 
            $art_entry['articles_title'], 
            $art_entry['articles_summary'],
            /*$art_entry['articles_category'], */
            $category_name,
            $art_entry['articles_content'],
            $art_entry['articles_members_only'],
            $art_entry['articles_authorised'], 
            $art_entry['articles_last_updated_on'],
            $file_uploaded_flg
         ));

    $cmtqry_result = ArticleComments($fd, $art_entry['articles_id']);
    if (!$cmtqry_result)
        continue;

    while (($cmt = mysql_fetch_array ($cmtqry_result, MYSQL_ASSOC)) )  {
        $cmtCnt++;
        fputcsv($fd_artcmtcsv,
          array(
            $cmt['article_comments_id'], 
            $art_entry['articles_id'],
            $cmt['article_comments_date'], 
            $cmt['article_comments_author_id'],
            $cmt['article_comments_author_name'],
            $cmt['article_comments_author_email'],
            $cmt['article_comments_comment'],
            $cmt['article_comments_authorized'],
            $cmt['article_comments_ip']));
    }

    /* Split the files, one for every 100 comments */
    if (($artCnt % 100) == 0)  {
        fclose ($fd_artcsv);
        fclose ($fd_artcmtcsv);

        $fd_artcsv = OpenCSVFile ("csv/article", $artCnt/100);
        $fd_artcmtcsv = OpenCSVFile ("csv/article_cmt", $artCnt/100);

        WriteCSVHeaders($fd_artcsv, $fd_artcmtcsv);
    }
}

printf ("%d articles with %d comments printed\n", $artCnt, $cmtCnt);

fclose ($fd_artcmtcsv);
fclose ($fd_artcsv);
mysql_close($fd);
?>
