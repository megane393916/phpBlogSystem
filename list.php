<?php
/**
 * list.php
 * 記事一覧ページ：年月フィルタ + ページネーション（10件/ページ）
 */

require_once __DIR__ . '/functions.php';

// ---- クエリパラメータの取得と検証 ----
$year = isset($_GET['year']) && ctype_digit($_GET['year']) ? (int) $_GET['year'] : null;
$month = isset($_GET['month']) && ctype_digit($_GET['month']) ? (int) $_GET['month'] : null;
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int) $_GET['page'] : 1;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';

// ---- 記事データ取得・フィルタリング ----
$allArticles = getArticles(TEXT_DIR);
// アーカイブ用ウィジェットには全記事データを元にしたものを渡す
$archive = buildArchive($allArticles);

// 検索キーワードがある場合は検索処理を実行
if ($q !== '') {
    $allArticles = searchArticles($allArticles, $q);
}

// タグ指定がある場合はタグで絞り込み
if ($tag !== '') {
    $allArticles = filterArticlesByTag($allArticles, $tag);
}

// その後、年月でフィルタリング
$filteredArticles = filterArticles($allArticles, $year, $month);
$totalCount = count($filteredArticles);

// ---- ページネーション計算 ----
$paging = calcPagination($totalCount, PER_PAGE, $page);
$currentPage = $paging['currentPage'];
$totalPages = $paging['totalPages'];
$offset = $paging['offset'];

$pagedArticles = array_slice($filteredArticles, $offset, PER_PAGE);

// ---- ページタイトル・フィルターラベル生成 ----
if ($q !== '') {
    $filterLabel = "「" . htmlspecialchars($q) . "」の検索結果";
    if ($year && $month) {
        $filterLabel .= "（{$year}年{$month}月）";
    } elseif ($year) {
        $filterLabel .= "（{$year}年）";
    }
} elseif ($tag !== '') {
    $filterLabel = "タグ「" . htmlspecialchars($tag) . "」の記事";
    if ($year && $month) {
        $filterLabel .= "（{$year}年{$month}月）";
    } elseif ($year) {
        $filterLabel .= "（{$year}年）";
    }
} else {
    if ($year && $month) {
        $filterLabel = "{$year}年{$month}月の記事";
    } elseif ($year) {
        $filterLabel = "{$year}年の記事";
    } else {
        $filterLabel = 'すべての記事';
    }
}

// ---- ページネーションURL生成ヘルパー ----
function buildPageUrl(int $p, ?int $year, ?int $month, string $q = '', string $tag = ''): string
{
    $params = ['page' => $p];
    if ($year)
        $params['year'] = $year;
    if ($month)
        $params['month'] = $month;
    if ($q !== '')
        $params['q'] = $q;
    if ($tag !== '')
        $params['tag'] = $tag;
    return 'list.php?' . http_build_query($params);
}

// OGP用のベースURL・デフォルト画像
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$defaultOgpImage = rtrim($baseUrl, '/') . '/images/background.jpg';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($filterLabel) ?> - <?= htmlspecialchars(BLOG_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars(BLOG_TITLE) ?>の<?= htmlspecialchars($filterLabel) ?>一覧ページです。">
    
    <!-- OGP Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($filterLabel) ?> - <?= htmlspecialchars(BLOG_TITLE) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(BLOG_TITLE) ?>の<?= htmlspecialchars($filterLabel) ?>一覧ページです。">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($defaultOgpImage) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars(BLOG_TITLE) ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <!-- ====== ヘッダー ====== -->
    <header class="site-header" id="site-header">
        <div class="header-inner">
            <a href="../index.html" class="site-logo" id="logo">
                <div class="logo-icon">✦</div>
                <span class="logo-text"><?= htmlspecialchars(BLOG_TITLE) ?></span>
            </a>
            <nav class="site-nav" id="site-nav">
                <a href="../index.html" id="nav-home">Home</a>
                <a href="list.php" id="nav-articles">記事一覧</a>
            </nav>
        </div>
    </header>

    <!-- ====== メインコンテンツ ====== -->
    <main class="page-wrapper" id="main-content">
        <div class="content-grid">

            <!-- 記事一覧エリア -->
            <section id="articles-section">

                <!-- ページヘッダー -->
                <div class="list-page-head" id="list-page-head">
                    <h2><?= htmlspecialchars($filterLabel) ?></h2>
                    <p class="filter-label">
                        全 <?= $totalCount ?> 件
                        <?php if ($totalPages > 1): ?>
                            ／ <?= $currentPage ?> / <?= $totalPages ?> ページ
                        <?php endif; ?>
                    </p>
                </div>

                <!-- ---- 記事カードリスト ---- -->
                <?php if (empty($pagedArticles)): ?>
                    <div class="empty-state glass-card" id="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>
                            <?php if ($year || $month): ?>
                                <?= htmlspecialchars($filterLabel) ?>の記事はありません。
                            <?php else: ?>
                                記事がまだありません。<code>text/</code> フォルダに .md ファイルを追加してください。
                            <?php endif; ?>
                        </p>
                        <a href="list.php" class="btn btn-ghost" style="margin-top:16px;" id="btn-clear-filter">
                            フィルターをクリア
                        </a>
                    </div>
                <?php else: ?>
                    <div class="article-list" id="article-list">
                        <?php foreach ($pagedArticles as $article): ?>
                            <article class="article-card glass-card"
                                id="article-card-<?= htmlspecialchars($article['slug']) ?>">
                                <div class="article-meta">
                                    <span class="article-date"><?= htmlspecialchars($article['date']) ?></span>
                                    <?php if (!empty($article['tags'])): ?>
                                        <span class="article-tags">
                                            <?php foreach ($article['tags'] as $t): ?>
                                                <a href="list.php?tag=<?= urlencode($t) ?>" class="tag-badge-small"><?= htmlspecialchars($t) ?></a>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h2>
                                    <a href="article.php?slug=<?= urlencode($article['slug']) ?>"
                                        id="article-link-<?= htmlspecialchars($article['slug']) ?>">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </a>
                                </h2>
                                <p class="article-preview">
                                    <?= htmlspecialchars($article['preview']) ?>
                                </p>
                                <a href="article.php?slug=<?= urlencode($article['slug']) ?>" class="read-more-link"
                                    id="read-more-<?= htmlspecialchars($article['slug']) ?>">
                                    続きを読む
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- ---- ページネーション ---- -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" id="pagination" aria-label="ページネーション">

                            <!-- 前へ -->
                            <?php if ($currentPage > 1): ?>
                                <a href="<?= htmlspecialchars(buildPageUrl($currentPage - 1, $year, $month, $q, $tag)) ?>"
                                    id="pagination-prev" aria-label="前のページ">‹</a>
                            <?php endif; ?>

                            <!-- ページ番号 -->
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            ?>

                            <?php if ($startPage > 1): ?>
                                <a href="<?= htmlspecialchars(buildPageUrl(1, $year, $month, $q, $tag)) ?>" id="pagination-first">1</a>
                                <?php if ($startPage > 2): ?>
                                    <span class="page-dots">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <?php if ($p === $currentPage): ?>
                                    <span class="current-page" id="pagination-current-<?= $p ?>" aria-current="page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars(buildPageUrl($p, $year, $month, $q, $tag)) ?>"
                                        id="pagination-page-<?= $p ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="page-dots">…</span>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(buildPageUrl($totalPages, $year, $month, $q, $tag)) ?>"
                                    id="pagination-last"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <!-- 次へ -->
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="<?= htmlspecialchars(buildPageUrl($currentPage + 1, $year, $month, $q, $tag)) ?>"
                                    id="pagination-next" aria-label="次のページ">›</a>
                            <?php endif; ?>

                        </nav>
                    <?php endif; ?>

                <?php endif; ?>

            </section>

            <!-- サイドバー -->
            <?php include __DIR__ . '/sidebar.php'; ?>

        </div>
    </main>

    <!-- ====== フッター ====== -->
    <footer class="site-footer" id="site-footer">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(BLOG_TITLE) ?></p>
    </footer>

    <!-- ページトップへ戻るボタン -->
    <button id="page-top-btn" aria-label="ページトップへ戻る">
        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
    </button>
    <script>
        const pageTopBtn = document.getElementById('page-top-btn');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                pageTopBtn.classList.add('show');
            } else {
                pageTopBtn.classList.remove('show');
            }
        });
        pageTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>

</html>