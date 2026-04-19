<?php
/**
 * functions.php
 * ブログシステム共通ロジック
 * - Markdownパーサー
 * - 記事データ取得・ソート
 * - アーカイブ集計
 */

// ============================================================
// 1. 軽量 Markdown → HTML パーサー
// ============================================================

/**
 * Markdown テーブルブロックをHTMLに変換するプリプロセッサ
 *
 * テーブル構造（例）:
 *   | 列A | 列B | 列C |
 *   |:---|:---:|---:|
 *   | データ | データ | データ |
 *
 * @param string $text  未変換のMarkdownテキスト
 * @return string       テーブル部分をHTMLに置換したテキスト
 */
function convertTables(string $text): string
{
    $lines = explode("\n", $text);
    $output = [];
    $i = 0;
    $total = count($lines);

    while ($i < $total) {
        $line = $lines[$i];

        // テーブル行かどうか：| を含む行
        if (!preg_match('/^\s*\|.+\|\s*$/', $line)) {
            $output[] = $line;
            $i++;
            continue;
        }

        // 次の行が区切り行（|---|---:|:---|...）か確認
        $nextIdx = $i + 1;
        if (
            $nextIdx >= $total
            || !preg_match('/^\s*\|[\s:\-|]+\|\s*$/', $lines[$nextIdx])
        ) {
            // 区切り行がなければ通常行として扱う
            $output[] = $line;
            $i++;
            continue;
        }

        // ---- テーブルブロック収集 ----
        $headerLine = $line;
        $separatorLine = $lines[$nextIdx];
        $i += 2;

        // アライメント解析（区切り行から列ごとのalignを決定）
        $sepCells = parseTableRow($separatorLine);
        $alignList = [];
        foreach ($sepCells as $sep) {
            $sep = trim($sep);
            $left = str_starts_with($sep, ':');
            $right = str_ends_with($sep, ':');
            if ($left && $right) {
                $alignList[] = 'center';
            } elseif ($right) {
                $alignList[] = 'right';
            } elseif ($left) {
                $alignList[] = 'left';
            } else {
                $alignList[] = '';  // デフォルト（指定なし）
            }
        }

        // ヘッダーHTML生成
        $headerCells = parseTableRow($headerLine);
        $thead = '<thead><tr>';
        foreach ($headerCells as $idx => $cell) {
            $align = $alignList[$idx] ?? '';
            $style = $align ? " style=\"text-align:{$align}\"" : '';
            $thead .= "<th{$style}>" . inlineMarkdown(trim($cell)) . '</th>';
        }
        $thead .= '</tr></thead>';

        // データ行HTML生成
        $tbody = '<tbody>';
        while ($i < $total && preg_match('/^\s*\|.+\|\s*$/', $lines[$i])) {
            $dataCells = parseTableRow($lines[$i]);
            $tbody .= '<tr>';
            foreach ($dataCells as $idx => $cell) {
                $align = $alignList[$idx] ?? '';
                $style = $align ? " style=\"text-align:{$align}\"" : '';
                // セル内の <br> タグを許可しつつインラインMarkdown変換
                $cellHtml = inlineMarkdownWithBr(trim($cell));
                $tbody .= "<td{$style}>{$cellHtml}</td>";
            }
            $tbody .= '</tr>';
            $i++;
        }
        $tbody .= '</tbody>';

        $output[] = "<div class=\"table-wrapper\"><table class=\"md-table\">{$thead}{$tbody}</table></div>";
    }

    return implode("\n", $output);
}

/**
 * テーブル行の文字列を分割してセルの配列を返す
 * 先頭・末尾の | を除いてから | で分割する
 *
 * @param string $row
 * @return array
 */
function parseTableRow(string $row): array
{
    $row = trim($row);
    // 先頭と末尾の | を除去
    if (str_starts_with($row, '|')) {
        $row = substr($row, 1);
    }
    if (str_ends_with($row, '|')) {
        $row = substr($row, 0, -1);
    }
    return explode('|', $row);
}

/**
 * インラインMarkdown変換（セル内 <br> タグを許可したバージョン）
 *
 * @param string $text
 * @return string
 */
function inlineMarkdownWithBr(string $text): string
{
    // <br> タグを一時的にプレースホルダーに退避
    $placeholder = '__BR_PLACEHOLDER__';
    $text = preg_replace('/<br\s*\/?>/i', $placeholder, $text);

    // 通常のインラインMarkdown変換（htmlspecialchars含む）
    $text = inlineMarkdown($text);

    // プレースホルダーを <br> に戻す
    $text = str_replace($placeholder, '<br>', $text);

    return $text;
}

/**
 * 見出しのテキストからHTMLのid属性用文字列（スラッグ）を生成する
 * 
 * @param string $text 見出しテキスト
 * @return string
 */
function generateHeaderId(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    // 文字、数字、ダッシュ、アンダースコア、半角スペース以外を削除（一般的なMarkdown目次リンクの仕様に準拠）
    $text = preg_replace('/[^\p{L}\p{M}\p{N}\p{Pd}\p{Pc}\- ]/u', '', $text);
    // 半角スペースをハイフンに変換
    $text = preg_replace('/ /', '-', $text);
    return $text;
}

/**
 * MarkdownテキストをHTMLに変換する
 *
 * @param string $text Markdownテキスト
 * @return string 変換後のHTML
 */
function parseMarkdown(string $text): string
{
    // テーブルブロックをHTMLに変換してからライン処理
    $text = convertTables($text);
    $lines = explode("\n", $text);
    $html = '';
    $listStack = [];
    $inCode = false;
    $inBlockquote = false;
    $codeBuffer = '';

    // 生HTMLブロック中かどうかのフラグ（div・iframeなどのパススルー用）
    $inRawHtml = false;
    $rawHtmlTagType = '';

    foreach ($lines as $line) {
        // --- コードブロック（``` で囲まれた範囲）---
        if (preg_match('/^```/', $line)) {
            if (!$inCode) {
                $inCode = true;
                $codeBuffer = '';
            } else {
                $html .= '<pre><code>' . htmlspecialchars($codeBuffer, ENT_QUOTES) . '</code></pre>' . "\n";
                $inCode = false;
                $codeBuffer = '';
            }
            continue;
        }

        if ($inCode) {
            $codeBuffer .= $line . "\n";
            continue;
        }

        // --- 生HTMLブロック（div / iframe）のパススルー ---
        if (!$inRawHtml && preg_match('/^\s*<(div|iframe)(\s|>)/i', $line, $m)) {
            $inRawHtml = true;
            $rawHtmlTagType = strtolower($m[1]);
        }
        if ($inRawHtml) {
            $html .= $line . "\n";
            // 開始したタグの種類に応じて終了判定
            if ($rawHtmlTagType === 'div') {
                if (preg_match('/<\/div>/i', $line)) {
                    $inRawHtml = false;
                }
            } elseif ($rawHtmlTagType === 'iframe') {
                if (preg_match('/<\/iframe>/i', $line) || preg_match('/<iframe[^>]*\/>/i', $line)) {
                    $inRawHtml = false;
                }
            }
            continue;
        }

        // --- リストの閉じ処理（リスト項目以外の行に移行時）---
        $isListLine = preg_match('/^(\s*)([\*\-]|(?:\d+\.))\s+/', $line);
        if (!$isListLine && !empty($listStack)) {
            while (!empty($listStack)) {
                $last = array_pop($listStack);
                $html .= '</' . $last['type'] . '>' . "\n";
            }
        }

        // --- 引用（Blockquote）の閉じ処理（引用以外の行に移行時）---
        if ($inBlockquote && !preg_match('/^>\s?/', $line)) {
            $html .= '</blockquote>' . "\n";
            $inBlockquote = false;
        }

        // --- Tags行を除外（本文として表示しない） ---
        if (preg_match('/^Tags:\s*(.+)/i', $line)) {
            continue;
        }

        // --- 見出し ---
        if (preg_match('/^###### (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h6 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h6>' . "\n";
            continue;
        }
        if (preg_match('/^##### (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h5 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h5>' . "\n";
            continue;
        }
        if (preg_match('/^#### (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h4 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h4>' . "\n";
            continue;
        }
        if (preg_match('/^### (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h3 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h3>' . "\n";
            continue;
        }
        if (preg_match('/^## (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h2 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h2>' . "\n";
            continue;
        }
        if (preg_match('/^# (.+)/', $line, $m)) {
            $id = htmlspecialchars(generateHeaderId($m[1]), ENT_QUOTES);
            $html .= '<h1 id="' . $id . '">' . inlineMarkdown($m[1]) . '</h1>' . "\n";
            continue;
        }

        // --- 水平線 ---
        if (preg_match('/^---+$/', trim($line))) {
            $html .= '<hr>' . "\n";
            continue;
        }

        // --- リスト処理（順序あり・なし階層対応） ---
        if ($isListLine && preg_match('/^(\s*)([\*\-]|(?:\d+\.))\s+(.*)/', $line, $m)) {
            $indent = strlen(str_replace("\t", "    ", $m[1]));
            $isOl = preg_match('/^\d+\./', $m[2]);
            $type = $isOl ? 'ol' : 'ul';
            $content = $m[3];

            // 現在のインデントより深い（内側の）リストを閉じる
            while (!empty($listStack) && end($listStack)['indent'] > $indent) {
                $last = array_pop($listStack);
                $html .= '</' . $last['type'] . '>' . "\n";
            }
            // 同レベルでタイプが違う場合は閉じる
            if (!empty($listStack) && end($listStack)['indent'] === $indent && end($listStack)['type'] !== $type) {
                $last = array_pop($listStack);
                $html .= '</' . $last['type'] . '>' . "\n";
            }
            // 新しいリスト階層を開始
            if (empty($listStack) || end($listStack)['indent'] < $indent) {
                $listStack[] = ['type' => $type, 'indent' => $indent];
                $html .= '<' . $type . '>' . "\n";
            }

            $html .= '<li>' . inlineMarkdown($content) . '</li>' . "\n";
            continue;
        }

        // --- 引用（Blockquote） ---
        if (preg_match('/^>\s?(.*)/', $line, $m)) {
            if (!$inBlockquote) {
                $html .= '<blockquote>' . "\n";
                $inBlockquote = true;
            }
            if (trim($m[1]) !== '') {
                $html .= '<p>' . inlineMarkdown($m[1]) . '</p>' . "\n";
            } else {
                $html .= '<br>' . "\n";
            }
            continue;
        }

        // --- 空行 ---
        if (trim($line) === '') {
            $html .= "\n";
            continue;
        }

        // --- 通常の段落 ---
        $html .= '<p>' . inlineMarkdown($line) . '</p>' . "\n";
    }

    // 閉じ忘れのリスト・引用を閉じる
    while (!empty($listStack)) {
        $last = array_pop($listStack);
        $html .= '</' . $last['type'] . '>' . "\n";
    }
    if ($inBlockquote)
        $html .= '</blockquote>' . "\n";

    return $html;
}

/**
 * インライン要素の Markdown 変換（太字・斜体・インラインコード・リンク・画像）
 *
 * @param string $text
 * @return string
 */
function inlineMarkdown(string $text): string
{
    // HTMLエスケープ後にMarkdown変換
    $text = htmlspecialchars($text, ENT_QUOTES);

    // インラインコード `code`
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

    // 太字 **text**
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

    // 斜体 *text*
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

    // 画像 ![alt](url)
    $text = preg_replace('/!\[(.*?)\]\((.+?)\)/', '<img src="$2" alt="$1" style="max-width: 100%; height: auto;" loading="lazy" />', $text);

    // リンク [text](url)
    $text = preg_replace('/(?<!!)\[(.+?)\]\((.+?)\)/', '<a href="$2" rel="noopener">$1</a>', $text);

    return $text;
}


// ============================================================
// 2. 記事データ取得・ソートロジック
// ============================================================

/**
 * text/ ディレクトリから記事リストを取得する
 *
 * @param string $textDir  .md ファイルが入ったディレクトリパス
 * @return array           記事情報の配列（タイムスタンプ降順）
 *   各要素 = [
 *     'slug'      => ファイル名（拡張子なし）
 *     'filepath'  => フルパス
 *     'title'     => 最初の # 行のテキスト
 *     'timestamp' => ファイル更新日時（Unixタイム）
 *     'date'      => 表示用日付 yyyy/mm/dd
 *     'year'      => 年（int）
 *     'month'     => 月（int）
 *     'preview'   => 本文プレビュー（最初の150文字程度）
 *   ]
 */
function getArticles(string $textDir): array
{
    $files = glob(rtrim($textDir, '/') . '/*.md');
    $articles = [];

    if (!$files) {
        return $articles;
    }

    foreach ($files as $filepath) {
        $slug = pathinfo($filepath, PATHINFO_FILENAME);
        $timestamp = filemtime($filepath);
        $date = date('Y/m/d', $timestamp);
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);

        $content = file_get_contents($filepath);
        $title = extractTitle($content);
        $preview = extractPreview($content);
        $tags = extractTags($content);

        $articles[] = [
            'slug' => $slug,
            'filepath' => $filepath,
            'title' => $title,
            'timestamp' => $timestamp,
            'date' => $date,
            'year' => $year,
            'month' => $month,
            'preview' => $preview,
            'tags' => $tags,
        ];
    }

    // タイムスタンプ降順ソート（新しい順）
    usort($articles, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

    return $articles;
}

/**
 * Markdownテキストから最初の # 見出しのタイトルを抽出する
 *
 * @param string $content
 * @return string
 */
function extractTitle(string $content): string
{
    if (preg_match('/^# (.+)/m', $content, $matches)) {
        return trim($matches[1]);
    }
    return '（タイトル未設定）';
}

/**
 * Markdownテキストからタグを抽出する
 *
 * @param string $content
 * @return array
 */
function extractTags(string $content): array
{
    if (preg_match('/^Tags:\s*(.+)$/im', $content, $matches)) {
        $tagsStr = $matches[1];
        // カンマ区切りで配列にし、前後の空白を削除
        $tags = array_map('trim', explode(',', $tagsStr));
        // 空のタグを除外
        return array_values(array_filter($tags, fn($t) => $t !== ''));
    }
    return [];
}

/**
 * Markdownテキストから本文プレビューを抽出する（見出し・空行を除いた最初の段落）
 *
 * @param string $content
 * @param int    $maxLength
 * @return string
 */
function extractPreview(string $content, int $maxLength = 150): string
{
    $lines = explode("\n", $content);
    $preview = '';

    foreach ($lines as $line) {
        $line = trim($line);
        // 見出し・コードブロック・空行・リスト記号・生HTMLタグ・Tags指定を除外
        if (
            $line === ''
            || str_starts_with($line, '#')
            || str_starts_with($line, '```')
            || preg_match('/^([\*\-]|(?:\d+\.))\s+/', $line)
            || stripos($line, 'Tags:') === 0
            || str_starts_with($line, '<')  // div・iframeなどの生HTMLはプレビューに含めない
        ) {
            continue;
        }

        // Markdownのインライン記号を除去して純テキストに
        $plain = strip_tags(inlineMarkdown($line));
        $preview .= $plain . ' ';

        if (mb_strlen($preview) >= $maxLength) {
            break;
        }
    }

    $preview = trim($preview);
    if (mb_strlen($preview) > $maxLength) {
        $preview = mb_substr($preview, 0, $maxLength) . '…';
    }

    return $preview;
}

/**
 * Markdown本文からOGP用サムネイル画像の絶対URLを抽出する
 * 1. YouTubeのiframeから動画IDを抽出し、公式高画質サムネイル画像URLを生成
 * 2. Markdown画像記法 または <img src> から最初の画像を抽出
 * 3. 相対パスだった場合は絶対URLに変換
 * 4. 画像が見つからない場合はデフォルト画像を返す
 *
 * @param string $content
 * @param string $baseUrl  (例: https://example.com/blog)
 * @return string
 */
function extractOgpImage(string $content, string $baseUrl): string
{
    // デフォルト画像 (変更する場合はここを修正)
    $defaultImage = rtrim($baseUrl, '/') . '/images/background.jpg';

    // 1. YouTubeのサムネイル抽出
    if (preg_match('/<iframe[^>]+src=["\']https:\/\/www\.youtube\.com\/embed\/([a-zA-Z0-9_\-]+)(?:\?[^"\']*)?["\']/i', $content, $m)) {
        return "https://img.youtube.com/vi/{$m[1]}/maxresdefault.jpg";
    }

    // 2. 一般画像の抽出
    $imageUrl = null;
    if (preg_match('/!\[.*?\]\((.+?)\)/', $content, $m)) {
        $imageUrl = $m[1];
    } else if (preg_match('/<img[^>]+src=["\'](.+?)["\']/i', $content, $m)) {
        $imageUrl = $m[1];
    }

    // 3. 絶対パス変換処理
    if ($imageUrl) {
        $imageUrl = trim($imageUrl);
        // すでに絶対URLならそのまま
        if (preg_match('/^(https?:)?\/\//i', $imageUrl)) {
            return $imageUrl;
        }
        // ルート相対パスの場合 (/)
        if (str_starts_with($imageUrl, '/')) {
            $parsed = parse_url($baseUrl);
            $domainUrl = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $domainUrl .= ':' . $parsed['port'];
            }
            return rtrim($domainUrl, '/') . '/' . ltrim($imageUrl, '/');
        }
        // 相対パスの場合
        return rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
    }

    return $defaultImage;
}

// ============================================================
// 3. アーカイブ集計（年別・月別）
// ============================================================

/**
 * 記事リストから年別・月別のアーカイブ集計を生成する
 *
 * @param array $articles  getArticles() の戻り値
 * @return array           ['year' => ['month' => count, ...], ...]（降順）
 */
function buildArchive(array $articles): array
{
    $archive = [];

    foreach ($articles as $article) {
        $y = $article['year'];
        $m = $article['month'];

        if (!isset($archive[$y])) {
            $archive[$y] = [];
        }
        if (!isset($archive[$y][$m])) {
            $archive[$y][$m] = 0;
        }
        $archive[$y][$m]++;
    }

    // 年を降順、月を降順にソート
    krsort($archive);
    foreach ($archive as &$months) {
        krsort($months);
    }
    unset($months);

    return $archive;
}


// ============================================================
// 4. フィルタリング・ページネーション
// ============================================================

/**
 * 記事リストを年・月でフィルタリングする
 *
 * @param array    $articles
 * @param int|null $year
 * @param int|null $month
 * @return array
 */
function filterArticles(array $articles, ?int $year, ?int $month): array
{
    if ($year === null && $month === null) {
        return $articles;
    }

    return array_values(array_filter($articles, function ($article) use ($year, $month) {
        $matchYear = ($year === null || $article['year'] === $year);
        $matchMonth = ($month === null || $article['month'] === $month);
        return $matchYear && $matchMonth;
    }));
}

/**
 * 記事リストをタグでフィルタリングする
 *
 * @param array    $articles
 * @param string   $tag
 * @return array
 */
function filterArticlesByTag(array $articles, string $tag): array
{
    if (trim($tag) === '') {
        return $articles;
    }
    return array_values(array_filter($articles, function ($article) use ($tag) {
        return in_array($tag, $article['tags'], true);
    }));
}

/**
 * 全記事のタグを集計する
 *
 * @param array $articles
 * @return array ['タグ名' => 記事件数, ...]
 */
function getAllTags(array $articles): array
{
    $tagsCount = [];
    foreach ($articles as $article) {
        if (!empty($article['tags'])) {
            foreach ($article['tags'] as $tag) {
                if (!isset($tagsCount[$tag])) {
                    $tagsCount[$tag] = 0;
                }
                $tagsCount[$tag]++;
            }
        }
    }
    arsort($tagsCount);
    return $tagsCount;
}

/**
 * 記事リストをキーワードで検索する（タイトルと本文）
 * スペース区切りで複数キーワード（AND検索）に対応
 *
 * @param array $articles
 * @param string $keyword
 * @return array
 */
function searchArticles(array $articles, string $keyword): array
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return $articles;
    }

    // 全角スペースを半角に変換し、連続するスペースを1つにしてから分割
    $keyword = mb_convert_kana($keyword, 's', 'UTF-8');
    $keywords = array_filter(explode(' ', $keyword), function($k) {
        return trim($k) !== '';
    });

    if (empty($keywords)) {
        return $articles;
    }

    $results = [];
    foreach ($articles as $article) {
        // 本文を読み込む（getArticlesではプレビューしか取得していないため）
        $content = file_get_contents($article['filepath']);
        $title = $article['title'];
        
        $isMatch = true;
        foreach ($keywords as $kw) {
            // タイトルまたは本文のいずれかにキーワードが含まれていればOK
            $kwMatch = (mb_stripos($title, $kw, 0, 'UTF-8') !== false) ||
                       (mb_stripos($content, $kw, 0, 'UTF-8') !== false);
            if (!$kwMatch) {
                $isMatch = false;
                break;
            }
        }

        if ($isMatch) {
            $results[] = $article;
        }
    }
    return $results;
}

/**
 * ページネーション情報を計算する
 *
 * @param int $totalItems  総件数
 * @param int $perPage     1ページあたり件数
 * @param int $currentPage 現在のページ（1始まり）
 * @return array ['totalPages', 'offset', 'currentPage']
 */
function calcPagination(int $totalItems, int $perPage, int $currentPage): array
{
    $totalPages = (int) ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages ?: 1));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'totalPages' => $totalPages,
        'offset' => $offset,
        'currentPage' => $currentPage,
    ];
}


// ============================================================
// 5. 定数・設定
// ============================================================

define('TEXT_DIR', __DIR__ . '/text');
define('BLOG_TITLE', 'わたしのブログ');
define('BLOG_TAGLINE', 'ブログの説明です');
define('PER_PAGE', 10);
define('TOP_COUNT', 5);
