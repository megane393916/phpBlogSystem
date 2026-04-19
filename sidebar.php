<?php
/**
 * sidebar.php
 * サイドバー共通コンポーネント
 * 呼び出し元で $archive（buildArchive() の戻り値）を宣言すること
 */

// $archive や $articles が未定義の場合はフォールバック
if (!isset($archive) || !isset($articles)) {
    require_once __DIR__ . '/functions.php';
    $articles = getArticles(TEXT_DIR);
    $archive  = buildArchive($articles);
}
// タグ一覧の生成
$allTags = getAllTags($articles);
?>
<aside class="sidebar" id="sidebar">

    <!-- ====== 検索ウィジェット ====== -->
    <div class="sidebar-widget glass-card" id="widget-search">
        <h3>Search</h3>
        <form action="list.php" method="get" class="search-form">
            <input type="text" name="q" placeholder="キーワードで検索" 
                   value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES) : '' ?>"
                   class="search-input">
            <button type="submit" class="btn btn-primary search-btn">検索</button>
        </form>
    </div>

    <!-- ====== アーカイブウィジェット ====== -->
    <div class="sidebar-widget glass-card" id="widget-archive">
        <h3>Archive</h3>

        <?php if (empty($archive)): ?>
            <p style="font-size:0.85rem;color:var(--text-muted);">記事がまだありません。</p>
        <?php else: ?>
            <?php foreach ($archive as $year => $months): ?>
                <div class="archive-year" id="archive-year-<?= $year ?>">
                    <div class="archive-year-label" onclick="toggleYear(<?= $year ?>)">
                        <?= $year ?>年
                        <span style="margin-left:auto;font-size:0.7rem;color:var(--text-muted);" id="arrow-<?= $year ?>">▼</span>
                    </div>
                    <div class="archive-months" id="months-<?= $year ?>">
                        <?php foreach ($months as $month => $count): ?>
                            <a href="list.php?year=<?= $year ?>&amp;month=<?= $month ?>"
                               id="archive-link-<?= $year ?>-<?= $month ?>">
                                <span><?= $month ?>月</span>
                                <span class="count"><?= $count ?>件</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ====== タグウィジェット ====== -->
    <div class="sidebar-widget glass-card" id="widget-tags">
        <h3>Tags</h3>
        <?php if (empty($allTags)): ?>
            <p style="font-size:0.85rem;color:var(--text-muted);">タグがまだありません。</p>
        <?php else: ?>
            <div class="tag-cloud">
                <?php foreach ($allTags as $tag => $count): ?>
                    <a href="list.php?tag=<?= urlencode($tag) ?>" class="tag-badge">
                        <?= htmlspecialchars($tag) ?> <span class="tag-count">(<?= $count ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====== 記事一覧へのリンクウィジェット ====== -->
    <div class="sidebar-widget glass-card" id="widget-links">
        <h3>Navigation</h3>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <a href="index.php" class="btn btn-ghost" id="nav-top"
               style="width:100%;justify-content:flex-start;">🏠 トップページ</a>
            <a href="list.php" class="btn btn-ghost" id="nav-list"
               style="width:100%;justify-content:flex-start;">📝 記事一覧</a>
        </div>
    </div>

</aside>

<script>
/**
 * 年別アーカイブの開閉トグル
 */
function toggleYear(year) {
    const el    = document.getElementById('months-' + year);
    const arrow = document.getElementById('arrow-' + year);
    if (!el) return;
    const isHidden = el.style.display === 'none';
    el.style.display  = isHidden ? 'block' : 'none';
    arrow.textContent = isHidden ? '▼' : '▶';
}
</script>
