<?php
/**
 * article.php
 * 記事詳細ページ：指定したslugのMarkdownを変換して表示
 */

require_once __DIR__ . '/functions.php';

// ---- セキュリティ：slugの検証 ----
$slug = isset($_GET['slug']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['slug']) : '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$filepath = TEXT_DIR . '/' . $slug . '.md';

if (!file_exists($filepath)) {
    header('HTTP/1.1 404 Not Found');
    $pageTitle = 'ページが見つかりません';
    $notFound = true;
} else {
    $notFound = false;
    $content = file_get_contents($filepath);
    $timestamp = filemtime($filepath);
    $date = date('Y/m/d', $timestamp);
    $title = extractTitle($content);
    $tags = extractTags($content);
    $pageTitle = $title;
    $htmlBody = parseMarkdown($content);
    
    // 現在のページURLおよびブログベースURLを取得（シェア用＆画像の絶対パス化用）
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // OGP画像の抽出
    $ogpImage = extractOgpImage($content, $baseUrl);
}

// サイドバー用アーカイブを準備
$articles = getArticles(TEXT_DIR);
$archive = buildArchive($articles);

// 前後の記事を特定（タイムスタンプ降順なのでindexで判定）
$prevArticle = null;
$nextArticle = null;
if (!$notFound) {
    foreach ($articles as $i => $art) {
        if ($art['slug'] === $slug) {
            $prevArticle = $articles[$i - 1] ?? null; // 新しい方（前）
            $nextArticle = $articles[$i + 1] ?? null; // 古い方（次）
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(BLOG_TITLE) ?></title>
    <?php if (!$notFound): ?>
        <meta name="description" content="<?= htmlspecialchars(extractPreview($content, 120)) ?>">
        
        <!-- OGP Tags -->
        <meta property="og:title" content="<?= htmlspecialchars($pageTitle . ' - ' . BLOG_TITLE) ?>">
        <meta property="og:description" content="<?= htmlspecialchars(extractPreview($content, 120)) ?>">
        <meta property="og:type" content="article">
        <meta property="og:url" content="<?= htmlspecialchars($currentUrl) ?>">
        <meta property="og:image" content="<?= htmlspecialchars($ogpImage) ?>">
        <meta property="og:site_name" content="<?= htmlspecialchars(BLOG_TITLE) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle . ' - ' . BLOG_TITLE) ?>">
        <meta name="twitter:description" content="<?= htmlspecialchars(extractPreview($content, 120)) ?>">
        <meta name="twitter:image" content="<?= htmlspecialchars($ogpImage) ?>">
    <?php endif; ?>
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

            <!-- 記事本文エリア -->
            <div id="article-area">
                <?php if ($notFound): ?>
                    <!-- 404エラー表示 -->
                    <div class="glass-card" style="padding:48px;text-align:center;" id="not-found">
                        <div style="font-size:4rem;margin-bottom:16px;">🔍</div>
                        <h1 style="font-size:1.6rem;margin-bottom:8px;">記事が見つかりません</h1>
                        <p style="color:var(--text-muted);margin-bottom:24px;">
                            指定された記事（<code><?= htmlspecialchars($slug) ?></code>）は存在しません。
                        </p>
                        <a href="index.php" class="btn btn-primary" id="btn-back-top">トップへ戻る</a>
                    </div>

                <?php else: ?>
                    <!-- 記事詳細 -->
                    <article class="article-detail glass-card" id="article-detail">
                        <!-- タイトル・メタ情報 -->
                        <div class="detail-head">
                            <div class="detail-date">
                                📅 <?= htmlspecialchars($date) ?>
                                <?php if (!empty($tags)): ?>
                                    <span class="article-tags" style="margin-left: 12px;">
                                        <?php foreach ($tags as $t): ?>
                                            <a href="list.php?tag=<?= urlencode($t) ?>" class="tag-badge-small"><?= htmlspecialchars($t) ?></a>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h1 class="detail-title"><?= htmlspecialchars($title) ?></h1>
                        </div>

                        <!-- Markdown 変換済みコンテンツ -->
                        <div class="md-content" id="article-body">
                            <?= $htmlBody ?>
                        </div>

                        <!-- SNSシェアボタン -->
                        <div class="sns-share">
                            <span class="share-label">シェアする:</span>
                            <a href="https://twitter.com/share?url=<?= urlencode($currentUrl) ?>&text=<?= urlencode($title . ' - ' . BLOG_TITLE) ?>" target="_blank" rel="noopener noreferrer" class="share-btn twitter">𝕏 Post</a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-btn facebook">Facebook</a>
                            <a href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer" class="share-btn line">LINE</a>
                            <button type="button" class="share-btn copy-url" onclick="copyArticleUrl(this)" data-url="<?= htmlspecialchars($currentUrl, ENT_QUOTES) ?>">URLコピー</button>
                        </div>

                        <!-- 前後記事ナビゲーション -->
                        <div class="article-footer-nav" id="article-footer-nav">
                            <div>
                                <?php if ($nextArticle): ?>
                                    <a href="article.php?slug=<?= urlencode($nextArticle['slug']) ?>" class="btn btn-ghost"
                                        id="btn-prev-article">
                                        ← <?= htmlspecialchars(mb_substr($nextArticle['title'], 0, 24)) ?>...
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($prevArticle): ?>
                                    <a href="article.php?slug=<?= urlencode($prevArticle['slug']) ?>" class="btn btn-ghost"
                                        id="btn-next-article">
                                        <?= htmlspecialchars(mb_substr($prevArticle['title'], 0, 24)) ?>... →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                    <!-- 一覧へ戻るボタン -->
                    <div style="margin-top:20px;text-align:center;">
                        <a href="list.php" class="btn btn-ghost" id="btn-back-list">← 記事一覧へ戻る</a>
                    </div>
                <?php endif; ?>
            </div>

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

        // URLコピー処理
        function copyArticleUrl(btn) {
            const url = btn.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const originalText = btn.textContent;
                btn.textContent = 'コピーしました！';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                alert('URLのコピーに失敗しました。');
            });
        }
    </script>
</body>

</html>