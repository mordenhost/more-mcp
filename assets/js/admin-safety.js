/**
 * Safety, API Tokens and Change History panels, and the "API tokens" section on
 * a user's profile. Talks to Admin\Safety_Controls through admin-ajax; the nonce,
 * URL, area list and strings come from the moreMcpSafety object.
 */
(function ($) {
	'use strict';

	var cfg = window.moreMcpSafety || {};
	var strings = cfg.strings || {};

	function post(action, data) {
		return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data || {}));
	}

	function failMessage(response) {
		return response && response.data && response.data.message ? response.data.message : strings.failed;
	}

	/** Show a short status next to a control, then let it fade. */
	function flash($status, text, isError) {
		$status.text(text).toggleClass('is-error', !!isError);
		if (!isError) {
			window.setTimeout(function () {
				$status.text('');
			}, 2500);
		}
	}

	// ------------------------------------------------------------------
	// Safety
	// ------------------------------------------------------------------

	var $safetyStatus = $('.mmcp-safety-status').first();

	function saveSafety(data, onFail) {
		flash($safetyStatus, strings.saving, false);
		post('more_mcp_set_safety', data)
			.done(function (response) {
				if (response && response.success) {
					flash($safetyStatus, strings.saved, false);
				} else {
					flash($safetyStatus, failMessage(response), true);
					if (onFail) { onFail(); }
				}
			})
			.fail(function () {
				flash($safetyStatus, strings.failed, true);
				if (onFail) { onFail(); }
			});
	}

	$(document).on('change', '.mmcp-safety-switch', function () {
		var $box = $(this);
		var key = $box.data('safety');
		var on = $box.is(':checked');

		if ('paused' === key && on && !window.confirm(strings.confirmPause)) {
			$box.prop('checked', false);
			return;
		}
		$box.closest('.mmcp-scope').toggleClass('is-on', on);

		var data = {};
		data[key] = on ? '1' : '0';
		saveSafety(data, function () {
			$box.prop('checked', !on).closest('.mmcp-scope').toggleClass('is-on', !on);
		});
	});

	$(document).on('change', '.mmcp-safety-select', function () {
		var data = {};
		data[$(this).data('safety')] = $(this).val();
		saveSafety(data);
	});

	$('#mmcp-safety-save-lists').on('click', function () {
		var data = {};
		$('textarea[data-safety]').each(function () {
			if (!this.disabled) {
				data[$(this).data('safety')] = $(this).val();
			}
		});
		saveSafety(data);
	});

	$(document).on('click', '.mmcp-approval-btn', function () {
		var $btn = $(this);
		var $row = $btn.closest('tr');
		var decision = $btn.data('decision');
		$row.find('.mmcp-approval-btn').prop('disabled', true);

		post('more_mcp_decide_approval', { id: $row.data('approval'), decision: decision })
			.done(function (response) {
				if (response && response.success) {
					$row.find('.mmcp-pill')
						.attr('class', 'mmcp-pill mmcp-pill-' + ('approve' === decision ? 'approved' : 'denied'))
						.text('approve' === decision ? 'approved' : 'denied');
					$row.find('.mmcp-col-action').empty();
					flash($safetyStatus, strings.saved, false);
				} else {
					flash($safetyStatus, failMessage(response), true);
					$row.find('.mmcp-approval-btn').prop('disabled', false);
				}
			})
			.fail(function () {
				flash($safetyStatus, strings.failed, true);
				$row.find('.mmcp-approval-btn').prop('disabled', false);
			});
	});

	// ------------------------------------------------------------------
	// Tokens
	// ------------------------------------------------------------------

	var $manager = $('.mmcp-token-manager').first();

	if ($manager.length) {
		var $tokenStatus = $manager.find('.mmcp-token-status');
		var $matrix = $manager.find('.mmcp-token-matrix');

		// One none / read / read-and-write selector per area, for the Custom level.
		$.each(cfg.groups || {}, function (group, label) {
			var id = 'mmcp-token-group-' + group;
			$matrix.append(
				$('<div class="mmcp-token-matrix-row"></div>')
					.append($('<label></label>').attr('for', id).text(label))
					.append(
						$('<select></select>').attr({ id: id, 'data-group': group })
							.append($('<option value="">').text('—'))
							.append($('<option value="read">').text(strings.read))
							.append($('<option value="write">').text(strings.write))
					)
			);
		});

		$('#mmcp-token-preset').on('change', function () {
			$manager.find('.mmcp-token-custom').prop('hidden', 'custom' !== $(this).val());
		});

		$('#mmcp-token-create').on('click', function () {
			var $btn = $(this);
			var label = $.trim($('#mmcp-token-label').val());
			if (!label) {
				$('#mmcp-token-label').trigger('focus');
				return;
			}

			var data = {
				label: label,
				preset: $('#mmcp-token-preset').val(),
				expires_in_days: $('#mmcp-token-expires').val(),
				rate_limit: $('#mmcp-token-rate').val() || 0
			};
			if ($('#mmcp-token-user').length) {
				data.user_id = $('#mmcp-token-user').val();
			}
			if ('custom' === data.preset) {
				data.groups = {};
				$matrix.find('select').each(function () {
					if ($(this).val()) {
						data.groups[$(this).data('group')] = $(this).val();
					}
				});
			}

			$btn.prop('disabled', true);
			flash($tokenStatus, strings.saving, false);
			post('more_mcp_create_token', data)
				.done(function (response) {
					if (response && response.success) {
						$('#mmcp-token-secret-value').val(response.data.token);
						$manager.find('.mmcp-token-form').prop('hidden', true);
						$manager.find('.mmcp-token-secret').prop('hidden', false);
						$('#mmcp-token-secret-value').trigger('focus').trigger('select');
						$tokenStatus.text('');
					} else {
						flash($tokenStatus, failMessage(response), true);
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					flash($tokenStatus, strings.failed, true);
					$btn.prop('disabled', false);
				});
		});

		$('#mmcp-token-copy').on('click', function () {
			var $btn = $(this);
			var $field = $('#mmcp-token-secret-value');
			$field.trigger('select');
			var done = function () {
				$btn.text(strings.copied);
				window.setTimeout(function () { $btn.text(strings.copy); }, 2000);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText($field.val()).then(done);
			} else {
				try { document.execCommand('copy'); done(); } catch (e) { /* selection stays highlighted for a manual copy */ }
			}
		});

		$('#mmcp-token-done').on('click', function () {
			$('#mmcp-token-secret-value').val('');
			window.location.reload();
		});

		$manager.on('click', '.mmcp-token-revoke, .mmcp-token-delete', function () {
			var $btn = $(this);
			var revoke = $btn.hasClass('mmcp-token-revoke');
			if (!window.confirm(revoke ? strings.confirmRevoke : strings.confirmDelete)) {
				return;
			}
			$btn.prop('disabled', true);
			post(revoke ? 'more_mcp_revoke_token' : 'more_mcp_delete_token', { id: $btn.closest('tr').data('token') })
				.done(function (response) {
					if (response && response.success) {
						window.location.reload();
					} else {
						window.alert(failMessage(response));
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					window.alert(strings.failed);
					$btn.prop('disabled', false);
				});
		});
	}

	$(document).on('change', '.mmcp-token-setting', function () {
		var $box = $(this);
		var on = $box.is(':checked');
		$box.closest('.mmcp-scope').toggleClass('is-on', on);
		post('more_mcp_token_settings', { self_service: on ? '1' : '0' }).fail(function () {
			$box.prop('checked', !on).closest('.mmcp-scope').toggleClass('is-on', !on);
		});
	});

	// ------------------------------------------------------------------
	// Change history
	// ------------------------------------------------------------------

	var $modal = $('#mmcp-history-modal');

	$(document).on('click', '.mmcp-history-diff', function () {
		var $btn = $(this).prop('disabled', true);
		post('more_mcp_history_diff', { id: $btn.closest('tr').data('entry') })
			.done(function (response) {
				if (response && response.success) {
					// The server escapes every value in this fragment (Safety_Controls::diff_html).
					$modal.find('.mmcp-modal-body').html(response.data.html);
					$modal.prop('hidden', false);
					$('#mmcp-history-modal-close').trigger('focus');
				} else {
					flash($safetyStatus, failMessage(response), true);
				}
			})
			.fail(function () {
				flash($safetyStatus, strings.failed, true);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$('#mmcp-history-modal-close').on('click', function () {
		$modal.prop('hidden', true);
	});
	$(document).on('keydown', function (event) {
		if ('Escape' === event.key && $modal.length && !$modal.prop('hidden')) {
			$modal.prop('hidden', true);
		}
	});

	function restore($btn, id, force) {
		$btn.prop('disabled', true);
		post('more_mcp_history_restore', { id: id, force: force ? '1' : '' })
			.done(function (response) {
				if (response && response.success) {
					flash($safetyStatus, strings.restored, false);
					window.setTimeout(function () { window.location.reload(); }, 700);
				} else if (response && response.data && response.data.changed && !force) {
					$btn.prop('disabled', false);
					if (window.confirm(strings.confirmForce)) {
						restore($btn, id, true);
					}
				} else {
					flash($safetyStatus, failMessage(response), true);
					$btn.prop('disabled', false);
				}
			})
			.fail(function () {
				flash($safetyStatus, strings.failed, true);
				$btn.prop('disabled', false);
			});
	}

	$(document).on('click', '.mmcp-history-restore', function () {
		var $btn = $(this);
		if (window.confirm(strings.confirmRestore)) {
			restore($btn, $btn.closest('tr').data('entry'), false);
		}
	});
}(jQuery));
