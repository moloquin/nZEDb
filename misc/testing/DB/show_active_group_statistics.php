<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nzedb\db\DB;

$pdo = new DB();
$count = $groups = 0;
if (!isset($argv[1])) {
	passthru("clear");
	exit(
		$pdo->log->error(
			"\nThis script will show all Active Groups. There is 1 required argument and 2 optional arguments.\n"
			. "The first argument of [date, releases] is used to sort the display by first_record_postdate or by the number of releases.\n"
			. "The second argument [ASC, DESC] sorts by ascending or descending.\n"
			. "The third argument will limit the return to that number of groups.\n"
			. "To sort the active groups by first_record_postdate and display only 20 groups run:\n"
			. "php $argv[0] date desc 20\n"
		)
	);
}
passthru("clear");
if (isset($argv[1]) && $argv[1] == "date") {
	$order = "order by first_record_postdate";
} else if (isset($argv[1]) && $argv[1] == "releases") {
	$order = "order by num_releases";
} else {
	$order = "";
}

if (isset($argv[2]) && ($argv[2] == "ASC" || $argv[2] == "asc")) {
	$sort = "ASC";
} else if (isset($argv[2]) && ($argv[2] == "DESC" || $argv[2] == "desc")) {
	$sort = "DESC";
} else {
	$sort = "";
}

if (isset($argv[3]) && is_numeric($argv[3])) {
	$limit = "LIMIT " . $argv[3];
} else if (isset($argv[2]) && is_numeric($argv[2])) {
	$limit = "LIMIT " . $argv[2];
} else {
	$limit = "";
}

$mask = $pdo->log->primary("%-50.50s %22.22s %22.22s %22.22s %22.22s %22.22s %22.22s %22.22s");
$releases = $pdo->queryDirect("SELECT name, backfill_target, first_record_postdate, last_updated, last_updated, CAST(last_record AS SIGNED)-CAST(first_record AS SIGNED) AS 'headers downloaded', TIMESTAMPDIFF(DAY,first_record_postdate,NOW()) AS days FROM groups");
if ($releases instanceof \Traversable) {
	foreach ($releases as $release) {
		$count += $release['headers downloaded'];
		$groups++;
	}
}

$active = $pdo->queryOneRow("SELECT COUNT(*) AS count FROM groups WHERE ACTIVE = 1");
printf($mask, "\nGroup Name => " . $active['count'] . "[" . $groups . "] (" . number_format($count) . " downloaded)", "Backfilled Days", "Oldest Post", "Last Updated", "Headers Downloaded", "Releases", "Renamed", "PreDB Matches");
printf($mask, "==================================================", "======================", "======================", "======================", "======================", "======================", "======================", "======================");

$releases = $pdo->queryDirect(
	sprintf("
		SELECT name, backfill_target, first_record_postdate, last_updated,
		CAST(last_record as SIGNED)-CAST(first_record as SIGNED) AS 'headers downloaded', TIMESTAMPDIFF(DAY,first_record_postdate,NOW()) AS days,
		COALESCE(rel.num, 0) AS num_releases,
		COALESCE(pre.num, 0) AS pre_matches,
		COALESCE(ren.num, 0) AS renamed FROM groups
		LEFT OUTER JOIN ( SELECT groups_id, COUNT(id) AS num FROM releases GROUP BY groups_id ) rel ON rel.groups_id = groups.id
		LEFT OUTER JOIN ( SELECT groups_id, COUNT(id) AS num FROM releases WHERE predb_id > 0 GROUP BY groups_id ) pre ON pre.groups_id = groups.id
		LEFT OUTER JOIN ( SELECT groups_id, COUNT(id) AS num FROM releases WHERE iscategorized = 1 GROUP BY groups_id ) ren ON ren.groups_id = groups.id
		WHERE active = 1 AND first_record_postdate %s %s %s", $order, $sort, $limit
	)
);

if ($releases instanceof \Traversable) {
	foreach ($releases as $release) {
		$headers = number_format($release['headers downloaded']);
		printf(
			$mask, $release['name'], $release['backfill_target'] . "(" . $release['days'] . ")",
			$release['first_record_postdate'], $release['last_updated'], $headers,
			number_format($release['num_releases']),
			$release['num_releases'] == 0 ? number_format($release['num_releases']) : number_format($release['renamed']) .
				"(" . floor($release['renamed'] / $release['num_releases'] * 100) . "%)",
			$release['num_releases'] == 0 ? number_format($release['num_releases']) : number_format($release['pre_matches']) .
				"(" . floor($release['pre_matches'] / $release['num_releases'] * 100) . "%)"
		);
	}
}
