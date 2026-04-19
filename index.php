<?php
/**
 * index.php
 * トップページ：新着記事を最大5件表示
 */

require_once __DIR__ . '/functions.php';

$articles = getArticles(TEXT_DIR);
$archive = buildArchive($articles);
$latestArticles = array_slice($articles, 0, TOP_COUNT);

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
    <title><?= htmlspecialchars(BLOG_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars(BLOG_TAGLINE) ?>">
    
    <!-- OGP Tags -->
    <meta property="og:title" content="<?= htmlspecialchars(BLOG_TITLE) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(BLOG_TAGLINE) ?>">
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

        <!-- ページバナー -->
        <div class="page-banner" id="page-banner">
            <h1><?= htmlspecialchars(BLOG_TITLE) ?></h1>
            <p class="tagline"><?= htmlspecialchars(BLOG_TAGLINE) ?></p>
        </div>

        <div class="content-grid">

            <!-- 記事一覧エリア -->
            <section id="latest-articles">
                <div class="list-page-head">
                    <h2>最新の記事</h2>
                    <p class="filter-label">最新 <?= TOP_COUNT ?> 件を表示しています</p>
                </div>

                <?php if (empty($latestArticles)): ?>
                    <div class="empty-state glass-card">
                        <div class="empty-icon">📭</div>
                        <p>記事がまだありません。<br><code>text/</code> フォルダに .md ファイルを追加してください。</p>
                    </div>
                <?php else: ?>
                    <div class="article-list" id="article-list">
                        <?php foreach ($latestArticles as $index => $article): ?>
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

                    <?php if (count($articles) > TOP_COUNT): ?>
                        <div style="text-align:center;margin-top:32px;">
                            <a href="list.php" class="btn btn-primary" id="btn-all-articles">
                                すべての記事を見る（<?= count($articles) ?>件）
                            </a>
                        </div>
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