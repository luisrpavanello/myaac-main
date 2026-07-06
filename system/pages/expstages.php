<?php
/**
 * Experience stages
 */
defined('MYAAC') or die('Direct access not allowed!');

$title = 'Experience Stages';

function myaac_parse_stages_block($content, $name)
{
	$stages = [];
	$start = strpos($content, $name . ' = {');
	if($start === false) {
		return $stages;
	}

	$section = substr($content, $start);
	if(preg_match('/\n\n[a-zA-Z]+Stages\s*=\s*\{/', $section, $next, PREG_OFFSET_CAPTURE, strlen($name))) {
		$section = substr($section, 0, $next[0][1]);
	}

	if(preg_match_all('/\{\s*(.*?)\s*\}/s', $section, $rows)) {
		foreach($rows[1] as $row) {
			$stage = [
				'minlevel' => null,
				'maxlevel' => null,
				'multiplier' => null,
			];

			foreach($stage as $key => $value) {
				if(preg_match('/' . $key . '\s*=\s*([0-9.]+)/', $row, $field)) {
					$stage[$key] = $field[1];
				}
			}

			if($stage['minlevel'] !== null && $stage['multiplier'] !== null) {
				$stages[] = $stage;
			}
		}
	}

	return $stages;
}

$stagesFile = rtrim($config['server_path'], '/\\') . '/data/stages.lua';
$stagesContent = is_readable($stagesFile) ? file_get_contents($stagesFile) : '';
$groups = [
	'Experience' => myaac_parse_stages_block($stagesContent, 'experienceStages'),
	'Skills' => myaac_parse_stages_block($stagesContent, 'skillsStages'),
	'Magic Level' => myaac_parse_stages_block($stagesContent, 'magicLevelStages'),
];
?>
<div class="TableContainer">
	<div class="CaptionContainer">
		<div class="CaptionInnerContainer">
			<span class="CaptionEdgeLeftTop" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-edge.gif);"></span>
			<span class="CaptionEdgeRightTop" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-edge.gif);"></span>
			<span class="CaptionBorderTop" style="background-image:url(<?= $template_path ?>/images/global/content/table-headline-border.gif);"></span>
			<span class="CaptionVerticalLeft" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-vertical.gif);"></span>
			<div class="Text">Experience Stages</div>
			<span class="CaptionVerticalRight" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-vertical.gif);"></span>
			<span class="CaptionBorderBottom" style="background-image:url(<?= $template_path ?>/images/global/content/table-headline-border.gif);"></span>
			<span class="CaptionEdgeLeftBottom" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-edge.gif);"></span>
			<span class="CaptionEdgeRightBottom" style="background-image:url(<?= $template_path ?>/images/global/content/box-frame-edge.gif);"></span>
		</div>
	</div>
	<table class="Table3" cellpadding="0" cellspacing="0">
		<tbody>
		<tr>
			<td>
				<div class="InnerTableContainer">
					<table style="width:100%;">
						<?php foreach($groups as $label => $stages): ?>
							<tr><td class="LabelV"><?= $label ?></td></tr>
							<tr>
								<td>
									<table class="TableContent" width="100%">
										<tr bgcolor="<?= $config['vdarkborder'] ?>">
											<td class="white"><b>From Level</b></td>
											<td class="white"><b>To Level</b></td>
											<td class="white"><b>Rate</b></td>
										</tr>
										<?php if(empty($stages)): ?>
											<tr bgcolor="<?= $config['lightborder'] ?>"><td colspan="3">No stages configured.</td></tr>
										<?php else: ?>
											<?php foreach($stages as $index => $stage): ?>
												<tr bgcolor="<?= getStyle($index + 1) ?>">
													<td><?= htmlspecialchars($stage['minlevel']) ?></td>
													<td><?= $stage['maxlevel'] === null ? 'Infinite' : htmlspecialchars($stage['maxlevel']) ?></td>
													<td><?= htmlspecialchars($stage['multiplier']) ?>x</td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</table>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			</td>
		</tr>
		</tbody>
	</table>
</div>
