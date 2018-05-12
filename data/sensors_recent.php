<?php if ($_GET['export']) {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=mjs.csv');
	print("sep=;\n");

} else {
	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html class="no-js">
	<head>
		<meta http-equiv="refresh" content="60">
	</head>
	<body>
		<table border="1">
			<tr>
				<th>ID</th>
				<th>Tijd</th>
				<th>Temp</th>
				<th>Luchtvocht</th>
				<th>Spanning</th>
				<th>Firmware</th>
				<th>Positie</th>
				<th>Fcnt</th>
				<th>Gateways</th>
				<th>RSSI</th>
				<th>LSNR</th>
				<th>Radiogegevens</th>
			</tr>
<?php
	}
	// connect to database
	include ("../connect.php");
	$database = Connection();
	
	// Ophalen van de meetdata van de lora sensoren
	if (isset($_GET['limit']))
		$limit = (int)$_GET['limit'];
	else
		$limit = 200;

	if (isset($_GET['sensor'])) {
		$sensor = (int)$_GET['sensor'];
		$WHERE = "WHERE msr.station_id = $sensor";
	} else {
		$WHERE = "";
	}

	$gateway_descriptions = [
		"eui-1dee0b64b020eec4" => "Meetjestad #1 (De WAR)",
		"eui-1dee17600247247a" => "Kroos & Co (Haaksbergen)",
		//"eui-1dee02b9f726633e" => "Meetjestad #3",
		"mjs-gateway-3" => "Meetjestad #3 (Berghotel)",
		"eui-1dee1cc11cba7539" => "Meetjestad #4 (De Koperhorst)",
		"eui-1dee18fc1c9d19d8" => "Meetjestad #5 (Berghotel)",
		"eui-1dee190506f53367" => "Meetjestad #6",
		"eui-0000024b080e020a" => "(NH Hotel Amersfoort)",
		"eui-0000024b080602ed" => "(De Bilt)",
		"eui-000078a504f5b057" => "(De Bilt)",
	];

	$result = $database->query("SELECT msr.*, msg.message FROM sensors_measurement AS msr LEFT JOIN sensors_message AS msg ON (msg.id = msr.message_id) $WHERE ORDER BY msr.timestamp DESC LIMIT $limit");
	print($database->error);
	$count_per_station = array();

	function output_cell($rowspan, $data) {
		if ($_GET['export']) {
			if ($_GET['comma_decimal'] && is_numeric($data)) {
				echo(str_replace('.', ',', $data) . ';');
			} else {
				echo($data . ';');
			}
		} else {
			echo("  <td rowspan=\"$rowspan\"> " . $data . "</td>\n");
		}
	}

	while($row = $result->fetch_array(MYSQLI_ASSOC)) {
		if ($row['message']) {
			$message = json_decode($row['message'], true);
			$metadata = $message['metadata'];

			if (!array_key_exists('gateways', $metadata)) {
				$gateways = [];

				// Convert TTN staging to production format
				foreach ($metadata as $meta) {
					$meta['gtw_id'] = 'eui-' . strtolower($meta['gateway_eui']);
					$meta['snr'] = $meta['lsnr'];
					$gateways[] = $meta;
				}

				$metadata = [
					'gateways' => $gateways,
					'frequency' => $metadata[0]['frequency'],
					'data_rate' => $metadata[0]['datarate'],
					'coding_rate' => $metadata[0]['codingrate'],
				];
			}

			$gateways = $metadata['gateways'];
			$rowspan = count($gateways);

			// Sort by LSR, descending
			usort($gateways, function($a, $b) { return $a['snr'] < $b['snr']; });
		} else {
			$message = [];
			$metadata = [];
			$gateways = [[]];
			$rowspan = 1;
		}

		if (array_key_exists($row['station_id'], $count_per_station))
			$count_per_station[$row['station_id']]++;
		else
			$count_per_station[$row['station_id']] = 1;

		$first = true;
		foreach ($gateways as $gwdata) {
			if (!$_GET['export'])
				echo("<tr>\n");
			if ($first) {

				$url = '?sensor=' . $row["station_id"] . '&amp;limit=50';

				if ($_GET['export'])
					output_cell($rowspan, $row["station_id"]);
				else
					output_cell($rowspan, "<a href=\"" . $url . "\">" . $row["station_id"] . "</a>");

				$datetime = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp'], new DateTimeZone('UTC'));
				$datetime->setTimeZone(new DateTImeZone('Europe/Amsterdam'));
				output_cell($rowspan, $datetime->format('Y-m-d H:i:s'));
				if ($_GET['export'])
					output_cell($rowspan, $row["temperature"]);
				else
					output_cell($rowspan, $row["temperature"] . "°C");
				if ($_GET['export'])
					output_cell($rowspan, $row["humidity"]);
				else
					output_cell($rowspan, $row["humidity"] . "%");
				if ($row['battery'] && $row['supply']) {
					if ($_GET['export']) {
						output_cell($rowspan, round($row["battery"],2));
						output_cell($rowspan, round($row['supply'],2));
					} else {
						output_cell($rowspan, round($row["battery"],2) . "V / " . round($row['supply'],2) . "V");
					}
				} else if ($row['supply']) {
					if ($_GET['export']) {
						output_cell($rowspan, '-');
						output_cell($rowspan, round($row['supply'],2));
					} else {
						output_cell($rowspan, round($row['supply'],2) . "V");
					}
				} else {
					if ($_GET['export'])
						output_cell($rowspan, '-');
					output_cell($rowspan, '-');
				}
				if ($row['firmware_version'] === null)
					output_cell($rowspan, '< v1');
				else
					output_cell($rowspan, 'v' . $row['firmware_version']);
				if ($row['latitude'] == '0.0' && $row['longitude'] == '0.0') {
					if ($_GET['export']) {
						output_cell($rowspan, '-');
						output_cell($rowspan, '-');
					}
					output_cell($rowspan, 'Geen positie');
				} else {
					$url = "http://www.openstreetmap.org/?mlat=" . $row['latitude'] . "&amp;mlon=" . $row['longitude'];
					if ($_GET['export']) {
						output_cell($rowspan, $row["latitude"]);
						output_cell($rowspan, $row["longitude"]);
					} else {
						output_cell($rowspan, "<a href=\"" . $url . "\">" . $row["latitude"] . " / " . $row["longitude"] . "</a>");
					}
				}
				if (array_key_exists('counter', $message)) {
					output_cell($rowspan, $message['counter']);
				} else {
					output_cell($rowspan, '-');
				}
			} else {
				//echo("  <td colspan=\"6\"></td>");
			}

			if (!$_GET['export']) {
				if (empty($gwdata)) {
					echo("  <td colspan=\"4\">Niet beschikbaar</a>");
				} else {
					$gw = $gwdata['gtw_id'];

					if (array_key_exists($gw, $gateway_descriptions))
						$gw .= "<br/>" . $gateway_descriptions[$gw];

					if ($gwdata['latitude'] && $gwdata['longitude']) {
						$url = "http://www.openstreetmap.org/?mlat=" . $gwdata['latitude'] . "&amp;mlon=" . $gwdata['longitude'];
						echo("  <td><a href=\"" . $url . "\">" . $gw . "</a></td>\n");
					} else {
						echo("  <td>" . $gw . "</td>\n");
					}
					echo("  <td>" . $gwdata["rssi"] . "</td>\n");
					echo("  <td>" . $gwdata["snr"] . "</td>\n");
					echo("  <td>" . $metadata["frequency"] . "Mhz, " . $metadata["data_rate"] . ", " .$metadata["coding_rate"] . "CR</td>\n");
				}
				echo("</tr>\n");
			} else {
				if ($first)
					echo("\n");
			}
			$first = false;
		}
	}
	if (!$_GET['export']) { ?>
		</table>
		<p><a href="?<?=$_SERVER['QUERY_STRING']?>&amp;export=1">export met punten</a></p>
		<p><a href="?<?=$_SERVER['QUERY_STRING']?>&amp;export=1&amp;comma_decimal=1">export met komma's</a></p>
		<p>Totaal: <?= count($count_per_station)?> meetstations</p>
		<table border="1">
		<tr><th>Station</th><th>Aantal berichten hierboven</th></tr>
		<?php
			ksort($count_per_station);
			foreach($count_per_station as $station => $count) {
				echo("<tr><td>$station</td><td>$count</td></tr>\n");
			}
		?>
		</table>
	</body>
</html>
<?php   } ?>
