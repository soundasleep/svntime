<?php

// note: this script only handles a single author currently, but it shouldn't be too much
// work to extend it to support multiple authors
// (just add a second level to each array, e.g. $analysed[author][] = array(...)

$svn_url = "https://openclerk.googlecode.com/svn/trunk";

$analysed = array();
$between_revisions = 60 * 60;	// how much of a gap to consider between revisions, seconds
$before_revision = 30 * 60;		// how much extra time to give before a revision
$after_revision = 30 * 60;		// how much extra time to give after a revision

$log = shell_exec("svn log -r 1:HEAD " . $svn_url);
$split = explode("\n", $log);
foreach ($split as $line) {

	$matches = false;
	if (preg_match("#^r([0-9]+) \\| ([^ ]+) \\| ([^\\|]+) \\(([^\\|]+)\\) \\| #i", $line, $matches)) {
		$analysed[] = array(
			'revision' => $matches[1],
			'author' => $matches[2],
			'date' => strtotime($matches[3]),
			'line' => $matches[0],
		);
	}

}

// print analysis as CSV
$fp = fopen("revisions.csv", "w");
fwrite($fp, csv_array(array("Revision", "Author", "Date")));
foreach ($analysed as $line) {
	fwrite($fp, csv_array(array($line['revision'], $line['author'], iso_date($line['date']))));
}
echo "Wrote revisions.csv with " . number_format(count($analysed)) . " revisions...\n";
fclose($fp);

// now that we have all of the revision times, we can calculate how long someone worked per day
$blocks = array();
$current_start_time = false;
$current_end_time = false;
$current_start_rev = false;
$current_end_rev = false;
$current_revisions = false;
$analysed[] = array('end' => true);
foreach ($analysed as $rev) {

	if ($current_end_time !== false) {
		// continue from the previous?
		if (!isset($rev['end']) && $rev['date'] < $current_end_time + $between_revisions) {
			// extend the previous end time
			$current_end_time = $rev['date'] + $after_revision;
			$current_end_rev = $rev['revision'];
			$current_revisions++;
			continue;
		} else {
			// we've got a new block
			$blocks[] = array(
				'start' => $current_start_time,
				'end' => $current_end_time,
				'start_revision' => $current_start_rev,
				'end_revision' => $current_end_rev,
				'revisions' => $current_revisions,
			);

			// continue through to reset
		}

	}

	if (isset($rev['end'])) {
		break;
	}

	$current_start_time = $rev['date'] - $before_revision;
	$current_end_time = $rev['date'] + $after_revision;
	$current_start_rev = $rev['revision'];
	$current_end_rev = $rev['revision'];
	$current_revisions = 1;

}

// print blocks as CSV
$fp = fopen("blocks.csv", "w");
fwrite($fp, csv_array(array("Start Date", "End Date", "Start Revision", "End Revision", "Revisions")));
foreach ($blocks as $line) {
	fwrite($fp, csv_array(array(iso_date($line['start']), iso_date($line['end']), $line['start_revision'], $line['end_revision'], $line['revisions'])));
}
echo "Wrote blocks.csv with " . number_format(count($blocks)) . " blocks...\n";
fclose($fp);

// take out blocks that overlap months
// but assumes no blocks span two months
$cleaned_blocks = array();
foreach ($blocks as $b) {
	if (date('Y-m', $b['start']) != date('Y-m', $b['end'])) {
		$cleaned_blocks[] = array(
			'start' => $b['start'],
			'end' => strtotime(date('Y-m', $b['end']) . "-01 00:00:00 -1 second"),		// one second will be lost
			'start_revision' => $b['start_revision'],
			'end_revision' => $b['end_revision'],
			'revisions' => $b['revisions'],
		);
		$cleaned_blocks[] = array(
			'start' => strtotime(date('Y-m', $b['end']) . "-01 00:00:00"),
			'end' => $b['end'],
			'start_revision' => $b['start_revision'],
			'end_revision' => $b['end_revision'],
			'revisions' => 0, // the second block will have "no" revisions
		);
	} else {
		$cleaned_blocks[] = $b;
	}
}

// print cleaned blocks as CSV
$fp = fopen("cleaned_blocks.csv", "w");
fwrite($fp, csv_array(array("Start Date", "End Date", "Start Revision", "End Revision", "Revisions")));
foreach ($cleaned_blocks as $line) {
	fwrite($fp, csv_array(array(iso_date($line['start']), iso_date($line['end']), $line['start_revision'], $line['end_revision'], $line['revisions'])));
}
echo "Wrote cleaned_blocks.csv with " . number_format(count($cleaned_blocks)) . " cleaned blocks...\n";
fclose($fp);

// now, we can calculate the amount worked per month
$months = array();
foreach ($cleaned_blocks as $line) {
	$m = date('Y-m', $line['start']);
	if (!isset($months[$m])) {
		$months[$m] = array(
			'blocks' => 0,
			'seconds' => 0,
			'revisions' => 0,
			'start' => $line['start'],
			'end' => $line['end'],
		);
	}

	$months[$m]['blocks']++;
	$months[$m]['seconds'] += ($line['end'] - $line['start']);
	$months[$m]['revisions'] += $line['revisions'];
	$months[$m]['start'] = min($months[$m]['start'], $line['start']);
	$months[$m]['end'] = max($months[$m]['end'], $line['end']);

}

// print months as CSV
$fp = fopen("months.csv", "w");
fwrite($fp, csv_array(array("Month", "Blocks", "Seconds", "Hours", "Revisions", "Block Start", "Block End")));
foreach ($months as $m => $line) {
	fwrite($fp, csv_array(array($m, $line['blocks'], $line['seconds'], $line['seconds'] / 3600, $line['revisions'], iso_date($line['start']), iso_date($line['end']))));
}
echo "Wrote months.csv with " . number_format(count($months)) . " months...\n";
fclose($fp);

function csv($s) {
	return "\"" . str_replace("\"", "\"\"", $s) . "\"";
}

function csv_array($array) {
	$r = array();
	foreach ($array as $value) {
		$r[] = csv($value);
	}
	return implode(",", $r) . "\n";
}

// a date format suitable for excel
function iso_date($d) {
	return date('Y-m-d H:i:s', $d);
}