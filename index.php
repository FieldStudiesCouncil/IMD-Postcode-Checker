<?php

// Get postcodes from the query string, sanitize the input and convert to an array
function get_postcodes_array() {
	$safe_postcodes = filter_input( INPUT_GET, 'p', FILTER_SANITIZE_STRING );
	if ( empty( $safe_postcodes ) ) {
		return array();
	}
	$safe_postcodes = explode( "\n", $safe_postcodes );
	return array_map( 'strtoupper', $safe_postcodes );
}

// Convert postcodes to a comma-delimited quoted list; e.g., 'TN33 0PF','BN4 1UH'
function postcodes_for_sql() {
	$postcodes = get_postcodes_array();
	$out       = '';
	foreach ( $postcodes as $postcode ) {
			$out .= "'" . trim( $postcode ) . "',";
	}
	return rtrim( $out, ',' );
}

// Convert postcodes from array to string with newline between each postcode
function postcodes_for_textarea() {
	$postcodes = get_postcodes_array();
	return implode( "\n", $postcodes );
}

$postcodes_for_sql = postcodes_for_sql();

$db = new PDO( 'sqlite:./db/imd.sqlite3' );

$imd_data_count = $db->query(
	"SELECT COUNT() FROM onspd_aug19 WHERE onspd_aug19.pcds IN ( $postcodes_for_sql )"
);

$imd_data = $db->query(
	"SELECT
		onspd.pcds,
		imd.lsoa_name_11,
		imd.imd_rank,
		imd.imd_decile
	FROM
		imd19 AS imd
	INNER JOIN onspd_aug19 AS onspd ON imd.lsoa_code_11 = onspd.lsoa11
	WHERE
		onspd.pcds IN (
			$postcodes_for_sql
		)"
	// AND imd.imd_decile = 1"
);

function output_table_row( $row, $fields, $red_or_green ) {
	$out = "<tr style='color:$red_or_green'>";
	foreach ( $fields as $field ) {
		$out .= '<td>' . $row[ $field ] . '</td>';
	}
	$out .= '</tr>';
	return $out;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>IMD Postcode Checker</title>
	<link rel="stylesheet" href="./style.css">
	</head>
<body>

<h1>IMD Postcode Checker</h1>

<!-- <details>
<summary>What is this?</summary>
The Indices of Multiple Deprivation.
</details> -->

<form action="./index.php" method="get">
	<label for="postcodes">
		Enter Postcodes<br>
		<span class="more-detail">Enter one postcode per line. Press the <i>Search IMD</i> button when ready to check them against the IMD.</span><br>
	</label>
	<textarea id="postcodes" name="p" rows="6\"><?php echo postcodes_for_textarea(); ?></textarea><br>
	<button type="submit">Search IMD</button>
</form><br>

<?php if ( ! empty( $_GET['p'] ) ) : ?>

<table border="0" cellspacing="0" cellpadding="3">
	<tr>
		<th>Postcode</th>
		<th>LSOA Name</th>
		<th>IMD Rank</th>
		<th>IMD Decile</th>
	</tr>

<?php endif; ?>

<?php

if ( ! empty( $_GET['p'] ) ) {

	$fields_to_output = array( 'pcds', 'lsoa_name_11', 'imd_rank', 'imd_decile' );

	$row_count = (int) $imd_data_count->fetchColumn();
	if ( $row_count > 0 ) {
		foreach ( $imd_data as $row ) {
			if ( $row['imd_decile'] === '1' ) {
				$red_or_green = '#222';
			} else {
				$red_or_green = '#bbb';
			}
			echo output_table_row( $row, $fields_to_output, $red_or_green );
		}
		echo '</table>';
	} else {
		echo '<tr><td colspan="' . count( $fields_to_output ) . '">No results found.</td></tr></table>';
	}
}

?>

</body>
</html>
