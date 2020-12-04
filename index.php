<?php

// Get postcodes from the query string, sanitize the input and convert to an
// array
function get_postcodes_array() {
	$safe_postcodes = filter_input( INPUT_GET, 'p', FILTER_SANITIZE_STRING );

	if ( empty( $safe_postcodes ) ) {
		return array();
	}

	$safe_postcodes = explode( "\n", $safe_postcodes );

	return array_map( 'strtoupper', $safe_postcodes );
}

// Get the decile value from the query string and validate it, or return a
// default value if there is no user input
function get_decile_int() {
	$options = array(
		'options' => array(
			'default'   => 10,
			'min_range' => 1,
			'max_range' => 10,
		),
	);

	return filter_input( INPUT_GET, 'd', FILTER_VALIDATE_INT, $options );
}

function add_quotes_and_comma( $str ) {
	if ( ! empty( $str ) ) {
		$str = trim( $str );
		return "'$str',";
	}
}

// Convert the postcodes to comma-delimited quoted list; e.g., 'TN33 0PF','BN4 1UH'
function postcodes_for_sql() {
	$postcodes = get_postcodes_array();
	$out       = '';

	foreach ( $postcodes as $postcode ) {
		$out .= add_quotes_and_comma( $postcode );
	}

	return rtrim( $out, ',' );
}

// Convert postcodes from array to string with newline between each postcode so
// they can be stuffed back into the textarea
function postcodes_for_textarea() {
	$postcodes = get_postcodes_array();

	return implode( "\n", $postcodes );
}

// Get either the current decile value from the query string, or an empty
// string. This is used to populate the decile input field.
function decile_for_input() {
	$decile = get_decile_int();
	if ( ! empty( $_GET['d'] ) ) {
		return $decile;
	} else {
		return '';
	}
}

// Function to render the table rows populated with database data.
function output_table_row( $row, $fields ) {
	$out = '<tr>';
	foreach ( $fields as $field ) {
		$out .= '<td>' . $row[ $field ] . '</td>';
	}
	$out .= '</tr>';
	return $out;
}

$postcodes_for_sql = postcodes_for_sql();
$decile_for_sql    = get_decile_int();

// Initialise the SQLite database
$db = new PDO( 'sqlite:./db/imd.sqlite3' );

// Get a count of postcodes matching those entered into the textarea. This is
// used prevent empty results from rendering.
$imd_data_count = $db->query(
	"SELECT COUNT() FROM onspd_aug19 WHERE onspd_aug19.pcds IN ( $postcodes_for_sql )"
);

// The main database query
$imd_data = $db->query(
	"SELECT
		onspd.pcds,
		imd.lsoa_name_11,
		imd.imd_rank,
		imd.imd_decile
	FROM
		imd19 AS imd
	INNER JOIN
		onspd_aug19 AS onspd ON imd.lsoa_code_11 = onspd.lsoa11
	WHERE
		onspd.pcds IN (	$postcodes_for_sql )
	AND
		imd.imd_decile <= $decile_for_sql"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="description" content="This simple tool enables you to look up the Index of Multiple Deprivation (IMD) rank for a list of postcodes.">
	<title>IMD Postcode Checker</title>
	<link rel="stylesheet" href="./style.css?v=4">
</head>

<body>

	<header>
		<h1>English IMD Postcode Checker</h1>
	</header>

	<main>

		<details>
			<summary>
				<h2>What is this?</h2>
			</summary>

			<p>This tool enables you to look up the Index of Multiple Deprivation rank for a list of postcodes. The lower the rank, the more deprived the area.</p>

			<p>The results can be limited to a maximum decile value. A <i>decile</i> is a range divided into 10 chunks similar to the way a percentage is a range divided into 100 chunks. A decile of 1 means the postcode is in the bottom 10% of the deprivation index, a decile of 2 means the postcode is in the bottom 20%, and so on.</p>

			<h3>What is the IMD?</h3>

			<p>The Index of Multiple Deprivation, commonly known as the IMD, is the official measure of relative deprivation for small areas in England.</p>

			<p>The Index of Multiple Deprivation ranks every small area, called lower-layer super output areas (LSOA), in
				England from 1 (most deprived area) to 32,844 (least deprived area).</p>

			<p>The IMD combines information from the seven domains to produce an overall relative measure of deprivation. The domains are combined using the following weights:</p>

			<ul>
				<li>Income Deprivation (22.5%)</li>
				<li>Employment Deprivation (22.5%)</li>
				<li>Education, Skills and Training Deprivation (13.5%)</li>
				<li>Health Deprivation and Disability (13.5%)</li>
				<li>Crime (9.3%)</li>
				<li>Barriers to Housing and Services (9.3%)</li>
				<li>Living Environment Deprivation (9.3%)</li>
			</ul>

			<h3>Data used in this tool</h3>

			<ul>
				<li><a href="https://www.ons.gov.uk/methodology/geography/geographicalproducts/postcodeproducts">ONS Postcode Directory (ONSPD)</a></li>
				<li><a href="https://www.gov.uk/government/statistics/english-indices-of-deprivation-2019">English Index of Multiple Deprivation 2019 (IMD)</a></li>
			</ul>
		</details>

		<form action="./index.php#data" method="get" class="flow">
			<label for="postcodes">
				Enter Postcodes<br>
				<span class="more-detail">Enter one postcode per line. Press the <i>Search IMD</i> button when ready to check them against the IMD.</span><br>
				<textarea id="postcodes" name="p" rows="6"><?php echo postcodes_for_textarea(); ?></textarea><br>
			</label>
			<label for="decile">
				Max Decile
				<span class="more-detail">Enter a number between 1 and 10, with 1 being the bottom 10%, 2 the bottom 20% and so on. <strong>Leave blank to include all deciles.</strong></span>
				<input type="number" min="1" max="10" name="d" id="decile" value="<?php echo decile_for_input(); ?>">
			</label>
			<button type="submit">Search IMD</button>
		</form><br>

		<?php if ( ! empty( $_GET['p'] ) ) : ?>

		<table id="data">
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
						echo output_table_row( $row, $fields_to_output );
					}
					echo '</table>';
				} else {
					echo '<tr><td colspan="' . count( $fields_to_output ) . '">No results found.</td></tr></table>';
				}
			}
			?>
	</main>

	<footer>
		<div class="footer-content">
			<p>The IMD Checker is a tiny thing made with <a href="https://gtmetrix.com/reports/www.fscbiodiversity.uk/NI7zKkRM">lean</a> but boring code, some open data, and plenty of ❤️.</p>
			<p>Copyright &copy; <?php echo gmdate( 'Y' ); ?> Charles Roper.</p>

			<p class="footer-separate github">
				<a href="https://github.com/charlesroper/IMD-Postcode-Checker">
					<svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" viewBox="0 0 136 133">
						<path fill="#fff" d="M68 0a68 68 0 00-22 132c4 1 5-1 5-3v-12c-19 5-23-9-23-9-3-7-7-10-7-10-7-4 0-4 0-4 7 1 10 7 10 7 6 11 16 8 20 6l4-9c-15-2-30-8-30-34 0-7 2-13 7-18-1-2-3-9 0-18 0 0 6-2 19 7a65 65 0 0134 0c13-9 19-7 19-7 3 9 1 16 0 18 5 5 7 11 7 18 0 26-16 32-31 34 3 2 5 6 5 12v19c0 2 1 4 4 3A68 68 0 0068 0" />
					</svg>Source available on GitHub.
				</a>
			</p>

			<p class="footer-separate">Contains OS data &copy; Crown copyright and database rights <?php echo gmdate( 'Y' ); ?></p>
			<p>Contains Royal Mail data © Royal Mail copyright and database rights <?php echo gmdate( 'Y' ); ?></p>
			<p>Contains National Statistics data © Crown copyright and database rights <?php echo gmdate( 'Y' ); ?></p>

			<p class="footer-separate">Made in The United Kingdom of Great Britain and Ireland, Europe.</p>
			<div class="flags">
				<svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" viewBox="0 0 512 342">
					<path fill="#f0f0f0" d="M0 0h512v341H0z" />
					<path fill="#d80027" fill-rule="nonzero" d="M288 0h-64v139H0v64h224v138h64V203h224v-64H288V0z" />
					<path fill="#039" fill-rule="nonzero" d="M394 230l118 66v-66H394z" />
					<path fill="#0052b4" fill-rule="nonzero" d="M312 230l200 111v-31l-144-80h-56z" />
					<path fill="#039" fill-rule="nonzero" d="M459 341l-147-81v81h147z" />
					<path fill="#f0f0f0" fill-rule="nonzero" d="M312 230l200 111v-31l-144-80h-56z" />
					<path fill="#d80027" fill-rule="nonzero" d="M312 230l200 111v-31l-144-80h-56z" />
					<path fill="#039" fill-rule="nonzero" d="M90 230L0 280v-50h90zM200 244v97H25l175-97z" />
					<path fill="#d80027" fill-rule="nonzero" d="M144 230L0 310v31l200-111h-56z" />
					<path fill="#039" fill-rule="nonzero" d="M118 111L0 46v65h118z" />
					<path fill="#0052b4" fill-rule="nonzero" d="M200 111L0 0v31l144 80h56z" />
					<path fill="#039" fill-rule="nonzero" d="M53 0l147 82V0H53z" />
					<path fill="#f0f0f0" fill-rule="nonzero" d="M200 111L0 0v31l144 80h56z" />
					<path fill="#d80027" fill-rule="nonzero" d="M200 111L0 0v31l144 80h56z" />
					<path fill="#039" fill-rule="nonzero" d="M422 111l90-50v50h-90zM312 97V0h175L312 97z" />
					<path fill="#d80027" fill-rule="nonzero" d="M368 111l144-80V0L312 111h56z" />
				</svg>
				<svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" viewBox="0 0 512 342">
					<path fill="#039" d="M0 0h512v341H0z" />
					<path fill="#fc0" fill-rule="nonzero" d="M256 38l-6 18 9 3-3-21z" />
					<path fill="#fc0" fill-rule="nonzero" d="M256 38l6 18-9 3 3-21z" />
					<path fill="#fc0" fill-rule="nonzero" d="M274 51h-19v10l19-10z" />
					<path fill="#fc0" fill-rule="nonzero" d="M274 51l-15 11-6-7 21-4z" />
					<g>
						<path fill="#fc0" fill-rule="nonzero" d="M267 72l-6-18-9 3 15 15z" />
						<path fill="#fc0" fill-rule="nonzero" d="M267 72l-15-11 5-8 10 19z" />
					</g>
					<g>
						<path fill="#fc0" fill-rule="nonzero" d="M238 51h19v10l-19-10z" />
						<path fill="#fc0" fill-rule="nonzero" d="M238 51l15 11 6-7-21-4z" />
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M245 72l6-18 9 3-15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M245 72l15-11-5-8-10 19z" />
						</g>
					</g>
					<g>
						<path fill="#fc0" fill-rule="nonzero" d="M256 265l-6 19 9 2-3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M256 265l6 19-9 2 3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M274 279h-19v9l19-9z" />
						<path fill="#fc0" fill-rule="nonzero" d="M274 279l-15 11-6-8 21-3z" />
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M267 300l-6-18-9 3 15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M267 300l-15-11 5-8 10 19z" />
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M238 279h19v9l-19-9z" />
							<path fill="#fc0" fill-rule="nonzero" d="M238 279l15 11 6-8-21-3z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M245 300l6-18 9 3-15 15z" />
								<path fill="#fc0" fill-rule="nonzero" d="M245 300l15-11-5-8-10 19z" />
							</g>
						</g>
					</g>
					<g>
						<path fill="#fc0" fill-rule="nonzero" d="M142 152l-6 18 9 3-3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M142 152l6 18-9 3 3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M160 165h-19v9l19-9z" />
						<path fill="#fc0" fill-rule="nonzero" d="M160 165l-15 11-6-8 21-3z" />
						<path fill="#fc0" fill-rule="nonzero" d="M153 186l-5-18-10 3 15 15z" />
						<path fill="#fc0" fill-rule="nonzero" d="M153 186l-15-11 6-8 9 19z" />
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M124 165h19v9l-19-9z" />
							<path fill="#fc0" fill-rule="nonzero" d="M124 165l16 11 5-8-21-3z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M131 186l6-18 9 3-15 15z" />
								<path fill="#fc0" fill-rule="nonzero" d="M131 186l15-11-5-8-10 19z" />
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M188 87l15-11-5-7-10 18z" />
							<path fill="#fc0" fill-rule="nonzero" d="M188 87l6-18 9 3-15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M181 66l15 11 6-7-21-4z" />
							<path fill="#fc0" fill-rule="nonzero" d="M181 66h19v10l-19-10z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M199 53l-6 18 9 3-3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M199 53l6 18-9 3 3-21z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M210 87l-15-11 5-7 10 18z" />
								<path fill="#fc0" fill-rule="nonzero" d="M210 87l-6-18-9 3 15 15z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M217 66l-15 11-6-7 21-4z" />
									<path fill="#fc0" fill-rule="nonzero" d="M217 66h-19v10l19-10z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M169 129l-6-18-9 3 15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M169 129l-16-11 6-8 10 19z" />
							<path fill="#fc0" fill-rule="nonzero" d="M146 129l16-11-6-8-10 19z" />
							<path fill="#fc0" fill-rule="nonzero" d="M146 129l6-18 9 3-15 15z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M139 108l16 11 5-8-21-3z" />
								<path fill="#fc0" fill-rule="nonzero" d="M139 108h19v9l-19-9z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M176 108l-16 11-5-8 21-3z" />
								<path fill="#fc0" fill-rule="nonzero" d="M176 108h-19v9l19-9z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M157 95l6 18-9 3 3-21z" />
									<path fill="#fc0" fill-rule="nonzero" d="M157 95l-5 18 9 3-4-21z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M176 222h-19v9l19-9z" />
							<path fill="#fc0" fill-rule="nonzero" d="M176 222l-16 11-5-8 21-3z" />
							<path fill="#fc0" fill-rule="nonzero" d="M169 243l-6-18-9 3 15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M169 243l-16-11 6-8 10 19z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M146 243l16-11-6-8-10 19z" />
								<path fill="#fc0" fill-rule="nonzero" d="M146 243l6-18 9 3-15 15z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M157 209l6 18-9 3 3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M157 209l-5 18 9 3-4-21z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M139 222h19v9l-19-9z" />
									<path fill="#fc0" fill-rule="nonzero" d="M139 222l16 11 5-8-21-3z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M217 263h-19v10l19-10z" />
							<path fill="#fc0" fill-rule="nonzero" d="M217 263l-15 11-6-7 21-4z" />
							<path fill="#fc0" fill-rule="nonzero" d="M210 285l-6-18-9 2 15 16z" />
							<path fill="#fc0" fill-rule="nonzero" d="M210 285l-15-12 5-7 10 19z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M188 285l15-12-5-7-10 19z" />
								<path fill="#fc0" fill-rule="nonzero" d="M188 285l6-18 9 2-15 16z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M199 250l6 18-9 3 3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M199 250l-6 18 9 3-3-21z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M181 263h19v10l-19-10z" />
									<path fill="#fc0" fill-rule="nonzero" d="M181 263l15 11 6-7-21-4z" />
								</g>
							</g>
						</g>
					</g>
					<g>
						<path fill="#fc0" fill-rule="nonzero" d="M370 152l6 18-9 3 3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M370 152l-6 18 9 3-3-21z" />
						<path fill="#fc0" fill-rule="nonzero" d="M352 165h19v9l-19-9z" />
						<path fill="#fc0" fill-rule="nonzero" d="M352 165l15 11 6-8-21-3z" />
						<path fill="#fc0" fill-rule="nonzero" d="M359 186l5-18 10 3-15 15z" />
						<path fill="#fc0" fill-rule="nonzero" d="M359 186l15-11-6-8-9 19z" />
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M388 165h-19v9l19-9z" />
							<path fill="#fc0" fill-rule="nonzero" d="M388 165l-16 11-5-8 21-3z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M381 186l-6-18-9 3 15 15z" />
								<path fill="#fc0" fill-rule="nonzero" d="M381 186l-15-11 5-8 10 19z" />
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M324 87l-15-11 5-7 10 18z" />
							<path fill="#fc0" fill-rule="nonzero" d="M324 87l-6-18-9 3 15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M331 66l-15 11-6-7 21-4z" />
							<path fill="#fc0" fill-rule="nonzero" d="M331 66h-19v10l19-10z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M313 53l6 18-9 3 3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M313 53l-6 18 9 3-3-21z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M302 87l15-11-5-7-10 18z" />
								<path fill="#fc0" fill-rule="nonzero" d="M302 87l6-18 9 3-15 15z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M295 66l15 11 6-7-21-4z" />
									<path fill="#fc0" fill-rule="nonzero" d="M295 66h19v10l-19-10z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M343 129l6-18 9 3-15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M343 129l16-11-6-8-10 19z" />
							<path fill="#fc0" fill-rule="nonzero" d="M366 129l-16-11 6-8 10 19z" />
							<path fill="#fc0" fill-rule="nonzero" d="M366 129l-6-18-9 3 15 15z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M373 108l-16 11-5-8 21-3z" />
								<path fill="#fc0" fill-rule="nonzero" d="M373 108h-19v9l19-9z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M336 108l16 11 5-8-21-3z" />
								<path fill="#fc0" fill-rule="nonzero" d="M336 108h19v9l-19-9z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M355 95l-6 18 9 3-3-21z" />
									<path fill="#fc0" fill-rule="nonzero" d="M355 95l5 18-9 3 4-21z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M336 222h19v9l-19-9z" />
							<path fill="#fc0" fill-rule="nonzero" d="M336 222l16 11 5-8-21-3z" />
							<path fill="#fc0" fill-rule="nonzero" d="M343 243l6-18 9 3-15 15z" />
							<path fill="#fc0" fill-rule="nonzero" d="M343 243l16-11-6-8-10 19z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M366 243l-16-11 6-8 10 19z" />
								<path fill="#fc0" fill-rule="nonzero" d="M366 243l-6-18-9 3 15 15z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M355 209l-6 18 9 3-3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M355 209l5 18-9 3 4-21z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M373 222h-19v9l19-9z" />
									<path fill="#fc0" fill-rule="nonzero" d="M373 222l-16 11-5-8 21-3z" />
								</g>
							</g>
						</g>
						<g>
							<path fill="#fc0" fill-rule="nonzero" d="M295 263h19v10l-19-10z" />
							<path fill="#fc0" fill-rule="nonzero" d="M295 263l15 11 6-7-21-4z" />
							<path fill="#fc0" fill-rule="nonzero" d="M302 285l6-18 9 2-15 16z" />
							<path fill="#fc0" fill-rule="nonzero" d="M302 285l15-12-5-7-10 19z" />
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M324 285l-15-12 5-7 10 19z" />
								<path fill="#fc0" fill-rule="nonzero" d="M324 285l-6-18-9 2 15 16z" />
							</g>
							<g>
								<path fill="#fc0" fill-rule="nonzero" d="M313 250l-6 18 9 3-3-21z" />
								<path fill="#fc0" fill-rule="nonzero" d="M313 250l6 18-9 3 3-21z" />
								<g>
									<path fill="#fc0" fill-rule="nonzero" d="M331 263h-19v10l19-10z" />
									<path fill="#fc0" fill-rule="nonzero" d="M331 263l-15 11-6-7 21-4z" />
								</g>
							</g>
						</g>
					</g>
				</svg>
			</div>
		</div>
	</footer>

</body>

</html>
