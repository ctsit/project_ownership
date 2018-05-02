<?php

define('PROJECT_OWNERSHIP_LIST_MAX_SIZE', 50);
define('PROJECT_OWNERSHIP_LIST_MAX_PAGER_SIZE', 10);

// Initialize page display object.
$objHtmlPage = new HtmlPage();
$objHtmlPage->addExternalJS(APP_PATH_JS . 'base.js');
$objHtmlPage->addStylesheet('jquery-ui.min.css', 'screen,print');
$objHtmlPage->addStylesheet('style.css', 'screen,print');
$objHtmlPage->addStylesheet('home.css', 'screen,print');
$objHtmlPage->PrintHeader();

// Displaying page tabs and title.
require_once APP_PATH_VIEWS . 'HomeTabs.php';
echo RCView::div(array('class' => 'projhdr'), RCView::img(array('src' => APP_PATH_IMAGES . 'key.png')) . ' Projects Ownership');

// Adding custom styles and scripts.
$module->includeCss('css/ownership-list.css');
$module->includeJs('js/ownership-list.js');

$curr_page = empty($_GET['pager']) || $_GET['pager'] != intval($_GET['pager']) ? 1 : $_GET['pager'];
$count_sql = 'SELECT COUNT(o.pid) as total_rows FROM redcap_project_ownership o
              INNER JOIN redcap_projects p ON p.project_id = o.pid';

$extra_fields = $extra_joins = '';
if (!SUPER_USER && !ACCOUNT_MANAGER) {
    $username = db_real_escape_string(USERID);
    $count_sql .= ' INNER JOIN redcap_user_rights u ON
                        u.project_id = p.project_id AND
                        u.username = "' . $username . '"';

    $extra_fields = ', ur.design user_design, r.design role_design';
    $extra_joins = ' INNER JOIN redcap_user_rights ur ON
                         ur.project_id = p.project_id AND
                         ur.username = "' . $username . '"
                     LEFT JOIN redcap_user_roles r ON r.role_id = ur.role_id';
}

// Getting total rows.
$q = $module->query($count_sql);
$total_rows = db_fetch_assoc($q);
$total_rows = $total_rows['total_rows'];

$sql = 'SELECT
            o.pid, o.username, o.firstname, o.lastname,
            p.app_title, p.creation_time, p.purpose, p.purpose_other, p.last_logged_event,
            u.user_firstname, u.user_lastname,
            p.project_pi_firstname, p.project_pi_lastname, p.project_irb_number' . $extra_fields . '
        FROM redcap_project_ownership o
        INNER JOIN redcap_projects p ON p.project_id = o.pid
        LEFT JOIN redcap_user_information u ON u.username = o.username' . $extra_joins . '
        ORDER BY o.pid DESC
        LIMIT ' . PROJECT_OWNERSHIP_LIST_MAX_SIZE . '
        OFFSET ' . ($curr_page - 1) * PROJECT_OWNERSHIP_LIST_MAX_SIZE;

// Getting rows.
$q = $module->query($sql);

if (!db_num_rows($q)) {
    echo RCView::p(array(), 'There are no projects to show.');
    $objHtmlPage->PrintFooter();
    exit;
}

$pager = array();
$rows = array();

$table_header = array(
    'Project #',
    'Project name',
    'Owner name',
    'PI name',
    'IRB #',
    'Purpose',
    'Records count',
    'Saved attributes count',
    'Upload file count',
    'Date of last activity',
    'Age since creation',
);

global $lang;

$purposes = array(
    RedCapDB::PURPOSE_PRACTICE => $lang['create_project_15'],
    RedCapDB::PURPOSE_OPS => $lang['create_project_16'],
    RedCapDB::PURPOSE_RESEARCH => $lang['create_project_17'],
    RedCapDB::PURPOSE_QUALITY => $lang['create_project_18'],
);

$now = date_create(NOW);
while ($row = db_fetch_assoc($q)) {
    $row = array_map('htmlspecialchars', $row);
    $links = array(
        APP_PATH_WEBROOT . 'index.php?pid=' . $row['pid'] => array('pid', 'project_name'),
    );

    if (!isset($row['user_design']) || $row['user_design'] || $row['role_design']) {
        $path = APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $row['pid'] . '&open_project_edit_popup=1';
        $links[$path] = array('owner_name', 'pi_name', 'irb', 'purpose');
    }

    if ($row['username']) {
        $row['firstname'] = $row['user_firstname'];
        $row['lastname'] = $row['user_lastname'];
    }

    $stats = $module->getProjectStats($row['pid']);
    $pi = $row['project_pi_firstname'] ? $row['project_pi_firstname'] . ' ' : '';
    $pi .= $row['project_pi_lastname'];

    $row = array(
        'pid' => $row['pid'],
        'project_name' => $row['app_title'],
        'owner_name' => $row['firstname'] . ' ' . $row['lastname'],
        'pi_name' => $pi ? $pi : '-',
        'irb' => $row['project_irb_number'] ? $row['project_irb_number'] : '-',
        'purpose' => $row['purpose'] == RedCapDB::PURPOSE_OTHER ? $row['purpose_other'] : $purposes[$row['purpose']],
        'records_count' => $stats['records_count'],
        'saved_attributes_count' => $stats['attr_count'],
        'upload_file_count' => $stats['file_uploads_count'],
        'date_of_last_activity' => date_create($row['last_logged_event'])->format('m/d/Y'),
        'age' => date_create($row['creation_time'])->diff($now)->format('%a days'),
    );

    foreach ($links as $path => $keys) {
        foreach ($keys as $key) {
            $row[$key] = RCView::a(array('href' => $path, 'target' => '_blank'), $row[$key]);
        }
    }

    $rows[] = $row;
}

// Setting up pager.
if ($total_rows > PROJECT_OWNERSHIP_LIST_MAX_SIZE) {
    $base_path = $module->getUrl('plugins/ownership_list.php') . '&pager=';

    // Including current page on pager.
    $pager[] = array(
        'url' => $module->getUrl('plugins/ownership_list.php?pager=' . $curr_page),
        'title' => $curr_page,
        'class' => 'active',
    );

    // Calculating the total number of pages.
    $num_pages = (int) ($total_rows / PROJECT_OWNERSHIP_LIST_MAX_SIZE);
    if ($total_rows % PROJECT_OWNERSHIP_LIST_MAX_SIZE) {
        $num_pages++;
    }

    // Calculating the pager size.
    $pager_size = PROJECT_OWNERSHIP_LIST_MAX_PAGER_SIZE;
    if ($num_pages < $pager_size) {
        $pager_size = $num_pages;
    }

    // Creating queue of items to prepend.
    $start = $curr_page - $pager_size > 1 ? $curr_page - $pager_size : 1;
    $end = $curr_page - 1;
    $queue_prev = $end >= $start ? range($start, $end) : array();

    // Creating queue of items to append.
    $start = $curr_page + 1;
    $end = $curr_page + $pager_size < $num_pages ? $curr_page + $pager_size : $num_pages;
    $queue_next = $end >= $start ? range($start, $end) : array();

    // Prepending and appending items until we reach the pager size.
    $remaining_items = $pager_size - 1;
    while ($remaining_items) {
        if (!empty($queue_next)) {
            $page_num = array_shift($queue_next);
            $pager[] = array(
                'title' => $page_num,
                'url' => $base_path . $page_num,
            );

            $remaining_items--;
        }

        if (!$remaining_items) {
            break;
        }

        if (!empty($queue_prev)) {
            $page_num = array_pop($queue_prev);
            array_unshift($pager, array(
                'title' => $page_num,
                'url' => $base_path . $page_num,
            ));

            $remaining_items--;
        }
    }

    $item = array(
        'title' => '...',
        'class' => 'disabled',
        'url' => '#',
    );

    if (!empty($queue_prev)) {
        array_unshift($pager, $item);
    }

    if (!empty($queue_next)) {
        $pager[] = $item;
    }

    // Adding "First" and "Prev" buttons.
    $prefixes = array(
        array(
            'title' => 'First',
            'url' => $base_path . '1',
        ),
        array(
            'title' => 'Prev',
            'url' => $base_path . ($curr_page - 1),
        ),
    );

    if ($curr_page == 1) {
        foreach (array_keys($prefixes) as $i) {
            $prefixes[$i]['class'] = 'disabled';
            $prefixes[$i]['url'] = '#';
        }
    }

    // Adding "Next" and "Last" buttons.
    $suffixes = array(
        array(
            'title' => 'Next',
            'url' => $base_path . ($curr_page + 1),
        ),
        array(
            'title' => 'Last',
            'url' => $base_path . $num_pages,
        ),
    );

    if ($curr_page == $num_pages) {
        foreach (array_keys($suffixes) as $i) {
            $suffixes[$i]['class'] = 'disabled';
            $suffixes[$i]['url'] = '#';
        }
    }

    $pager = array_merge($prefixes, $pager, $suffixes);
}
?>

<div id="project-ownership-list" class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <?php foreach ($table_header as $value): ?>
                    <th><?php echo $value; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php foreach ($row as $value): ?>
                    <td><?php echo $value; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php if (!empty($pager)): ?>
    <nav aria-label="Project Ownership List Navigation">
        <ul class="pagination">
            <?php foreach ($pager as $page): ?>
                <li class="page-item<?php echo $page['class'] ? ' ' . $page['class'] : ''; ?>">
                    <a class="page-link" href="<?php echo $page['url']; ?>"><?php echo $page['title']; ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
<?php $objHtmlPage->PrintFooter(); ?>
