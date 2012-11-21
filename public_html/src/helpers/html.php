<?php
class HtmlHelper extends ChibiHelper {
	public function tableHeadStartDate() { ?>
		<th class="start-date">
			<a class="tooltipable"
			href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'start-date', 'sort-dir' => $this->view->sortColumn == 'start-date' ? 1 - $this->view->sortDir : 0]) ?>">
				Started
			</a>
		</th><?php
	}

	public function tableHeadFinishDate() { ?>
		<th class="finish-date">
			<a href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'finish-date', 'sort-dir' => $this->view->sortColumn == 'finish-date' ? 1 - $this->view->sortDir : 0]) ?>">
				Finished
			</a>
		</th><?php
	}

	public function tableHeadLength() { ?>
		<th class="length">
			<a class="tooltipable"
			<?php if ($this->view->am == AMModel::ENTRY_TYPE_ANIME): ?>
				data-title="Length (episodes)"
			<?php else: ?>
				data-title="Length (volumes)"
			<?php endif ?>
			data-position-my="center bottom"
			data-position-at="center top"
			href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'length', 'sort-dir' => $this->view->sortColumn == 'length' ? 1 - $this->view->sortDir : 0]) ?>">
				L
			</a>
		</th><?php
	}

	public function tableHeadScore() { ?>
		<th class="score">
			<a href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'score', 'sort-dir' => $this->view->sortColumn == 'score' ? 1 - $this->view->sortDir : 0]) ?>">
				Score
			</a>
		</th><?php
	}

	public function tableHeadStatus() { ?>
		<th class="status">
			<a class="tooltipable"
				data-title="Status"
				data-position-my="center bottom"
				data-position-at="center top"
				href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'status', 'sort-dir' => $this->view->sortColumn == 'status' ? 1 - $this->view->sortDir : 0]) ?>">
				S
			</a>
		</th><?php
	}

	public function tableHeadTitle() { ?>
		<th class="title">
			<a href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'score', 'sort-dir' => $this->view->sortColumn == 'score' ? 1 - $this->view->sortDir : 0]) ?>">
				Title
			</a>
		</th><?php
	}

	public function tableHeadUnique() { ?>
		<?php if (count($this->view->users) > 1): ?>
			<th class="unique">
				<a class="tooltip tooltipable"
					data-title="Unique - marked with &bdquo;+&rdquo; if only this user has given title in their list."
					data-position-my="center bottom"
					data-position-at="center top"
					href="<?php echo $this->mgHelper->constructUrl(null, null, ['sort-column' => 'unique', 'sort-dir' => $this->view->sortColumn == 'unique' ? 1 - $this->view->sortDir : 0]) ?>">
					U
				</a>
			</th>
		<?php endif ?><?php
	}



	public function tableBodyStartDate($e) { ?>
		<td class="started">
			<?php echo $e['user']['start-date'] ?>
		</td><?php
	}

	public function tableBodyFinishDate($e) { ?>
		<td class="finish">
			<?php echo $e['user']['finish-date'] ?>
		</td><?php
	}

	public function tableBodyLength($e) { ?>
		<td class="length">
			<?php if ($this->view->am == AMModel::ENTRY_TYPE_ANIME): ?>
				<?php echo $e['full']['episodes'] ?>
			<?php else: ?>
				<?php echo $e['full']['volumes'] ?>
			<?php endif ?>
		</td><?php
	}

	public function tableBodyScore($e) { ?>
		<?php if (!empty($e['others']) and count($e['others']) == 1): ?>
			<?php $e2 = reset($e['others']) ?>
			<?php if ($e['user']['score'] > $e2['user']['score']): ?>
				<?php if ($e2['user']['score'] > 0): ?>
					<td class="score higher-score">
						<span class="tooltipable" data-title="This title was rated lower by the other user.">
				<?php else: ?>
					<td class="score">
						<span class="tooltipable" data-title="This title wasn't rated by the other user.">
				<?php endif ?>
			<?php elseif ($e['user']['score'] < $e2['user']['score']): ?>
				<?php if ($e['user']['score'] > 0): ?>
					<td class="score lower-score">
						<span class="tooltipable" data-title="This title was rated higher by the other user.">
				<?php else: ?>
					<td class="score">
						<span class="tooltipable" data-title="This title wasn't rated by this user.">
				<?php endif ?>
			<?php else: ?>
				<?php if ($e['user']['score'] > 0): ?>
					<td class="score same-score">
						<span class="tooltipable" data-title="Both users rated this title equally.">
				<?php else: ?>
					<td class="score same-score">
						<span class="tooltipable" data-title="None of users rated this title.">
				<?php endif ?>
			<?php endif ?>
		<?php else: ?>
			<td class="score">
				<span>
		<?php endif ?>
			<?php if (empty($e['user']['score'])): ?>
				-
			<?php else: ?>
				<?php echo $e['user']['score'] ?>
			<?php endif ?>
			</span>
		</td><?php
	}

	public function tableBodyStatus($e) { ?>
		<td class="status">
			<i
			<?php if ($e['user']['status'] == UserModel::USER_LIST_STATUS_PLANNED): ?>
				data-title="Planned" class="icon-schedule tooltipable"
			<?php elseif ($e['user']['status'] == UserModel::USER_LIST_STATUS_DROPPED): ?>
				data-title="Dropped" class="icon-cross tooltipable"
			<?php elseif ($e['user']['status'] == UserModel::USER_LIST_STATUS_WATCHING): ?>
				data-title="Watching" class="icon-eye tooltipable"
			<?php elseif ($e['user']['status'] == UserModel::USER_LIST_STATUS_ONHOLD): ?>
				data-title="On-hold" class="icon-hourglass tooltipable"
			<?php elseif ($e['user']['status'] == UserModel::USER_LIST_STATUS_COMPLETED): ?>
				data-title="Completed" class="icon-tick tooltipable"
			<?php endif ?>
			></i>
		</td><?php
	}

	public function tableBodyTitle($e) { ?>
		<td class="title">
			<a href="http://myanimelist.net/<?php echo $this->view->mgHelper->amText() ?>/<?php echo $e['full']['id'] ?>">
				<?php echo $e['full']['title'] ?>
			</a>
		</td><?php
	}

	public function tableBodyUnique($e) { ?>
		<?php if (count($this->view->users) > 1): ?>
			<td class="unique">
				<?php if ($e['user']['unique']): ?>
					<i data-title="Only this user has this title on his list" class="tooltipable icon-plus"></i>
				<?php else: ?>
					<i data-title="Both users have this title on their lists" class="tooltipable icon-dot"></i>
				<?php endif ?>
			</td>
		<?php endif ?><?php
	}


}
