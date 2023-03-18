/*
 * post-hover-370.js - post-hover.js & post-hover-tree.js mashed into one
 * https://370ch.lt/js/post-hover-370.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/post-hover-370.js';
 *
 *   Also some scripts have been edited to make them work with the
 *   post-hover-tree stuff like inline-expanding.js & local-time.js
 */

$(document).ready(function(){
	if (localStorage.posthover_delayon == null) {
		localStorage.posthover_delayon = '50';
	}
	if (localStorage.posthover_delayoff == null) {
		localStorage.posthover_delayoff = '200';
	}
	if (window.Options && Options.get_tab('general')) {
		Options.extend_tab("general", "<fieldset id='post-hover'><legend>"+_('Post Hover')+'</legend>'+
			_('Delay until the message preview is shown:')+
									  "<select id='posthover-delayon'>" +
									  "<option value='0'>"+_('Not')+"</option>" +
									  "<option value='50'>50ms</option>" +
									  "<option value='100'>100ms</option>" +
									  "<option value='200'>200ms</option>" +
									  "<option value='300'>300ms</option>" +
									  "<option value='400'>400ms</option>" +
									  "<option value='500'>500ms</option>"+
									  "</select></br>"+
			_("Delay until the view of the message is closed:")+
									  "<select id='posthover-delayoff'>" +
									  "<option value='100'>100ms</option>" +
									  "<option value='200'>200ms</option>" +
									  "<option value='500'>500ms</option>" +
									  "<option value='800'>800ms</option>" +
									  "<option value='1000'>1000ms</option>" +
									  "<option value='2000'>2000ms</option>" +
									  "<option value='3000'>3000ms</option>" +
									  "<option value='5000'>5000ms</option>" +
									  "</select>"+
			"<label id='posthover-opt'><input type='checkbox' />"+_('Use the old message preview method')+"</label>");
		$('#posthover-delayon').val(localStorage.posthover_delayon).on('change', function(e) {
			localStorage.posthover_delayon = e.target.value;
		});
		$('#posthover-delayoff').val(localStorage.posthover_delayoff).on('change', function(e) {
			localStorage.posthover_delayoff = e.target.value;
		});
		$('#posthover-opt>input').on('change', function() {
			if (localStorage.posthover_opt === 'true') {
				localStorage.posthover_opt = 'false';
				$('#posthover-delayon').parent().fadeTo("slow", 1);
				$('#posthover-delayoff').parent().fadeTo("slow", 1);
			} else {
				localStorage.posthover_opt = 'true';
				$('#posthover-delayon').parent().fadeTo("slow", 0.33);
				$('#posthover-delayoff').parent().fadeTo("slow", 0.33);
			}
		});
		if (localStorage.posthover_opt === 'true') {
			$('#posthover-opt>input').attr('checked', 'checked');
			$('#posthover-delayon').parent().fadeTo("slow", 0.33);
			$('#posthover-delayoff').parent().fadeTo("slow", 0.33);
		}
	}
	if (localStorage.posthover_opt === 'true') {
		PostHover();
	} else {
		PostHoverTree();
	}
	
	function PostHoverTree() {
	/* post-hover-tree.js - Post hover tree. Because post-hover.js isn't russian enough.
	 * sauce: rfch.rocks/rfch.xyz + Some edits from me ^_^
	 *
	 * Known bugs:
	 * 1) Re-fetch single thread for different posts;
	 * 2) No right 'dead zone';
	 */
		//hovering time before opening preview (ms)
		rollOnDelay = localStorage.posthover_delayon;
		//timeout for closing inactive previews (ms)
		rollOverDelay = localStorage.posthover_delayoff;
		//minimal distance in pixels between post preview and the screen edge
		deadZone = 20;

		//end of 'settings'.

		var hovering = false;
		//var dont_fetch_again = [];
		var toFetch = {}; //{url: [post id list]}
		var rollOnTimer = null;
		/*
		function _debug(text) {
			if (window.FUKURO_DEBUG) {
				console.info(text);
			}
		}
		*/
		function Message(type, text) {
			var className;
			switch (type) {
				case 'error':
					className = 'bg-error'; break;
				case 'warning':
					className = 'bg-warning'; break;
				default:
					className = 'bg-info';
			}
			return $('<p class="'+className+'">'+text+'</p>');
		}

		function PostStub(id, content) {
			var $stub =
				$('<div class="post reply row post-hover stub" id="hover_reply_' + id + '"></div>');
			if (content) {
				$stub.append(content);
			}
			return $stub;
		}

		function summonPost(link) {
			var matches = $(link).text().match(/^>>(?:>\/([^\/]+)\/)?(\d+)$/);
			var id = matches[2];
			// _debug('Summoning '+id+"'s clone");
			//first search for hover
			var $hover = $("#hover_reply_"+id);
			if ($hover.length !== 0) {
				return $hover[0];
			}
			//then search for post in document
			var $post = $('#reply_'+id);
			var $op = $('#op_'+id);
			if ($.contains($post, link)) {
				return false;
			}
			if ($post.length !== 0) {
				return $post.clone().removeClass('highlighted').addClass('post-hover').attr('id', 'hover_reply_'+id)[0];
			}
			else if ($op.length !== 0) {
				var $opP = $op.clone();
				$op.siblings('div.files').clone().insertAfter($opP.find('p.intro'));
				if ($op.siblings('div.files').find('div.multifile').length) {
					$opP.find('div.body').css( "clear", "both" );
				}
				return $opP.removeClass('op').addClass('post-hover').addClass('reply').attr('id', 'hover_reply_'+id)[0];
			}
			//then try to retrieve it via ajax
			$post = PostStub(id);
			var url = $(link).attr('href').replace(/#.*$/, '');
			/*
			if ($.inArray(url, dont_fetch_again) != -1) {
				return $post.append(Message('warning', 'Š�Š¾Ń�Ń‚ Š½Šµ Š½Š°Š¹Š´ŠµŠ½.'));
			}
			dont_fetch_again.push(url);
			*/
			//push post id to fetch list if not already there
			if (!toFetch[url]) {
				toFetch[url] = [];
			}
			if ($.inArray(id, toFetch[url]) == -1) {
				toFetch[url].push(id);
			}
			// _debug('Fetching '+url+'...');
			$.ajax({
				url: url,
				context: document.body,
				success: function (data) {
					// _debug('Successfully fetched ' + url);
					/*
					$(data).find('div.post.reply').each(function () {
						if ($('#' + $(this).attr('id[1]')).length == 0)
							$('body').prepend($(this).css('display', 'none'));
					});
					*/
					var fetchList = toFetch[url];
					var $thread = $(data);
					for (var i= 0, l=fetchList.length; i<l; i++) {
						var id = fetchList[i];
						var $post = $thread.find('#reply_'+id);
						var $op = $thread.find('#op_'+id);
						var $pHolder = $('#hover_reply_' + id); //#placeholder?
						if (!$pHolder.length) {
							console.warn('No placeholder for ' + id + '! This is a bug.');
							continue;
						}
						if ($post.length) {
							if ($post.find('div.multifile').length) {
								$post.find('div.body').css( "clear", "both" );
							}
							$('body').prepend($post.css('display', 'none'));
							//replace placeholder with post clone
							$pHolder.empty().append($post.clone().contents()).removeClass('stub');
							position(null, $pHolder, null);
						}
						else if ($op.length) {
							if ($op.siblings('div.files').find('div.multifile').length) {
								$op.find('div.body').css( "clear", "both" );
							}
							$op.siblings('div.files').insertAfter($op.find('p.intro'));
							$('body').prepend($op.css('display', 'none'));
							$pHolder.empty().append($op.clone().contents()).removeClass('stub');
							position(null, $pHolder, null);
						}
						else {
							//replace placeholder with an error.
							$pHolder.empty().append(Message('warning', 'Å½inutÄ— nerasta ;_;'));
						}
						$(document).trigger('rus_hover', $pHolder);
					}
					delete toFetch[url];
				},
				error: function(jqXHR, textStatus, errorThrown) {
					var message;
					switch (jqXHR.status) {
						case 404:
							//TODO: keep non-existent thread ids or error messages.
							message = Message('warning', _('The theme does not exist ;_;'));
							break;
						default:
							message = Message('warning', _('Something went wrong ;_;'));
					}
					var fetchList = toFetch[url];
					for (var i= 0, l=fetchList.length; i<l; i++) {
						var id = fetchList[i];
						var $pHolder = $('#hover_reply_' + id); //DRY?
						if (!$pHolder.length) {
							console.warn('No placeholder for ' + id + '! This is a bug.');
							continue;
						}
						$pHolder.empty().append(message);
					}
					delete toFetch[url];
				}
			});
			return $post.append(Message('info', _('Loading...')))[0];
		}

		var chainCtrl = {
			tail: null,
			activeTail: null,
			_timeout: null,

			//appends post preview in correct place
			//returns true if preview position in DOM changed
			open: function(parent, post) {
				//_debug('Opening preview '+parent.id+'->'+post.id);
				var clearAfter = undefined;
				var moved = false;
				if ($(parent).is('.post-hover')) {
					if ($(parent).next()[0] != post) {
						clearAfter = parent;
					}
				}
				else {
					if ($('.post-hover')[0] != post) {
						clearAfter = null; //All previews
					}
				}
				if (clearAfter !== undefined) {
					this._clear(clearAfter);
				}
				if (!this.tail || this.tail == parent) {
					$('body').append(post);
					this.tail = post;
					moved = true;
				}
				this.inPost(post);
				return moved;
			},

			inPost: function(post){
				//set active tail
				//_debug('Setting active post to '+(post?post.id:'null'));
				this.activeTail = post;
				//[re]launch the clear timer
				clearTimeout(this._timeout);
				if (post != this.tail) {
					this._timeout = setTimeout(this._clear.bind(this), rollOverDelay);
				}
			},

			out: function() {
				this.inPost(null);
			},
			//removes hover subchain beginning from clearRoot's child
			_clear: function(clearAfter) {
				//if root is unspecified, clear from active tail
				if (clearAfter === undefined) {
					clearAfter = this.activeTail;
				}
				if (clearAfter !== null) {
					// _debug('Removing chain after ' + clearAfter.id);
					$(clearAfter).nextAll('.post-hover').fadeOut(160, function() {$(this).remove();});
					this.tail = clearAfter;
				}
				else {
					// _debug('Clearing entire chain.');
					$('.post-hover').fadeOut(160, function() {$(this).remove();});
					this.tail = null;
				}
			}
		};

		// Backup for 'frozen' previews (which should not appear normally)
		// http://stackoverflow.com/a/7385673
		$(document).mouseup(function (e) {
			if (!$(".post-hover").is(e.target) && $(".post-hover").has(e.target).length === 0) {
				setTimeout(function () {
					$(".post-hover").fadeOut(160, function() {$(this).remove();});
				}, 0);
				hovering = false;
			}
		});


		function init_hover_tree(target) {

			$(target).delegate('div.body > a , .mentioned > a, .oekakinfo a:not(.neoreplay):not(.neocontinue)', 'mouseenter' , linkEnter);
			$(target).delegate('div.body > a , .mentioned > a, .oekakinfo a:not(.neoreplay):not(.neocontinue)', 'mouseleave' , hoverLeave);
			$(target).delegate('div.post.post-hover', 'mouseenter', hoverEnter);
			$(target).delegate('div.post.post-hover', 'mouseleave', hoverLeave);
		}

		var linkEnter = function(evnt)
		{
			var link = this;
			//if (!summon(id) { //retrieve url; //summonAjax(url, id) }
			if (! /^>>(?:>\/([^\/]+)\/)?(\d+)$/.test($(this).text())) {
				//Just regular link. Skip it.
				return true;
			}
			clearTimeout(rollOnTimer);
			rollOnTimer = setTimeout(function() {
				var post = summonPost(link);
				if (post) {
					var parent = $(link).closest('div.post')[0];
					if (chainCtrl.open(parent, post)) {
						position($(link), $(post).hide(), evnt);
						$(post).fadeIn(160);
						$(document).trigger('rus_hover', post);
					}
				}
			}, rollOnDelay);
		};

		var hoverEnter = function(evnt)
		{
			if (!$(evnt.target).is('div.body > a, .mentioned > a')) {
				//links are handled by linkOver
				chainCtrl.inPost(this);
			}
		};

		var hoverLeave = function(evnt)
		{
			clearTimeout(rollOnTimer);
			//mouse move to links completely processed by linkOver
			if (evnt.relatedTarget && !$(evnt.relatedTarget).is('div.body > a, .mentioned > a')) {
				var $toPost = $(evnt.relatedTarget).closest('.post-hover');
				if ($toPost.length != 0) {
					chainCtrl.inPost($toPost[0]);
					return;
				}
			}
			//else
			chainCtrl.out();
		};

		//credits for original function to GhostPerson
		var position = function(link, newPost, evnt) {
			newPost.css({
				//use jQuery .show() instead (less style-dependend)
				//'display': 'block',
				'position': 'absolute',
				'border': '1px solid',
				//margins prevent precise positioning
				'margin-top': 0,
				'margin-left': 0
			});

			//a bit more complex positioning
			if (!position.direction)
				position.direction = 'down';
			//TODO: reset direction on preview clear?

			//save data for delayed position
			if (newPost.hasClass('stub')) {
				newPost.data('positionInfo', {
					evnt: evnt,
					link: link
				});
			}
			//recover data for delayed position
			if (!evnt) {
				var info = newPost.data('positionInfo');
				evnt = info.evnt;
				link = info.link;
				newPost.removeData('positionInfo');
			}

			var viewportHigh = evnt.clientY;
			var viewportLow = $(window).height() - viewportHigh;

			function positionUp() {
				newPost.css('top', link.offset().top - newPost.outerHeight());
			}
			function positionDown() {
				newPost.css('top', link.offset().top + link.outerHeight());
			}

			switch (position.direction) {
				case 'down':
					if (newPost.outerHeight() + deadZone > viewportLow) {
						position.direction = 'up';
						positionUp();
					}
					else {
						positionDown();
					}
					break;

				case 'up':
					if (newPost.outerHeight() + deadZone > viewportHigh) {
						position.direction = 'down';
						positionDown();
					}
					else {
						positionUp();
					}
					break;

				default:
					console.error('now you fucked up');
			}

			//simple horizontal positioning
			function positionLeft() {
				newPost.css({
					'left': Math.max(
						link.offset().left + link.outerWidth() - newPost.outerWidth(),
						deadZone),
					'right': 'auto'
				});
			}
			function positionRight() {
				newPost.css({
					'left': Math.min(link.offset().left, $(window).width() - newPost.outerWidth()/* - deadZone*/),
					'right': 'auto'
				});
			}

			var viewportRight = $(window).width() - evnt.clientX;
			var viewportLeft = $(window).width() - viewportRight;

			if (viewportRight > viewportLeft) {
				positionRight();
			}
			else {
				positionLeft();
			}
		};

		init_hover_tree(document);

		// allow to work with auto-reload.js, etc.
		//no need in this now, "delegate" takes care of everything
		
		$(document).bind('new_post', function (e, post) {
		init_hover_tree(post);
		});
	}
	
	function PostHover() {
	/* post-hover.js
	 * sauce: https://github.com/vichan-devel/vichan/blob/master/js/post-hover.js
	 *
	 * Released under the MIT license
	 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
	 * Copyright (c) 2013-2014 Marcin Å�abanowski <marcin@6irc.net>
	 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
	 */
		var dont_fetch_again = [];
		init_hover = function() {
			var $link = $(this);
			
			var id;
			var matches;

			if ($link.is('[data-thread]')) {
					id = $link.attr('data-thread');
			}
			else if(matches = $link.text().match(/^>>(?:>\/([^\/]+)\/)?(\d+)$/)) {
				id = matches[2];
			}
			else {
				return;
			}
			
			var board = $(this);
			while (board.data('board') === undefined) {
				board = board.parent();
			}
			var threadid;
			if ($link.is('[data-thread]')) threadid = 0;
			else threadid = board.attr('id').replace("thread_", "");

			board = board.data('board');

			var parentboard = board;
			
			if ($link.is('[data-thread]')) parentboard = $('form[name="post"] input[name="board"]').val();
			else if (matches[1] !== undefined) board = matches[1];

			var $post = false;
			var hovering = false;
			var hovered_at;
			$link.hover(function(e) {
				hovering = true;
				hovered_at = {'x': e.pageX, 'y': e.pageY};
				
				var start_hover = function($link) {
					if($.contains($post[0])) {
						// link links to itself or to op; ignore
					}
					else if ($post.is(':visible') &&
							$post.offset().top >= $(window).scrollTop() &&
							$post.offset().top + $post.height() <= $(window).scrollTop() + $(window).height()) {
						// post is in view
						$post.addClass('highlighted');
					} else {
						var $newPost = $post.clone();
						$newPost.find('>.reply, >br').remove();
						//$newPost.find('span.mentioned').remove();
						$newPost.find('a.post_anchor').remove();

						$newPost						
							.attr('id', 'post-hover-' + id)
							.attr('data-board', board)
							.addClass('post-hover')
							.css('border-style', 'solid')
							.css('display', 'inline-block')
							.css('position', 'absolute')
							.css('font-style', 'normal')
							.css('z-index', '29')
							.css('margin-left', '1em')
							.addClass('reply').addClass('post')
							.insertAfter($link.parent())

						$link.trigger('mousemove');
					}
				};
				
				$post = $('[data-board="' + board + '"] div.post#reply_' + id + ', [data-board="' + board + '"]div#thread_' + id);
				if($post.length > 0) {
					start_hover($(this));
				} else {
					var url = $link.attr('href').replace(/#.*$/, '');
					
					if($.inArray(url, dont_fetch_again) != -1) {
						return;
					}
					dont_fetch_again.push(url);
					
					$.ajax({
						url: url,
						context: document.body,
						success: function(data) {
							var mythreadid = $(data).find('div[id^="thread_"]').attr('id').replace("thread_", "");

							if (mythreadid == threadid && parentboard == board) {
								$(data).find('div.post.reply').each(function() {
									if($('[data-board="' + board + '"] #' + $(this).attr('id')).length == 0) {
										$('[data-board="' + board + '"]#thread_' + threadid + " .post.reply:first").before($(this).hide().addClass('hidden'));
									}
								});
							}
							else if ($('[data-board="' + board + '"]#thread_'+mythreadid).length > 0) {
								$(data).find('div.post.reply').each(function() {
									if($('[data-board="' + board + '"] #' + $(this).attr('id')).length == 0) {
										$('[data-board="' + board + '"]#thread_' + mythreadid + " .post.reply:first").before($(this).hide().addClass('hidden'));
									}
								});
							}
							else {
								$(data).find('div[id^="thread_"]').hide().attr('data-cached', 'yes').prependTo('form[name="postcontrols"]');
							}

							$post = $('[data-board="' + board + '"] div.post#reply_' + id + ', [data-board="' + board + '"]div#thread_' + id);

							if(hovering && $post.length > 0) {
								start_hover($link);
							}
						}
					});
				}
			}, function() {
				hovering = false;
				if(!$post)
					return;			
				$post.removeClass('highlighted');
				if($post.hasClass('hidden') || $post.data('cached') == 'yes')
					$post.css('display', 'none');
				$('.post-hover').remove();
			}).mousemove(function(e) {
				if(!$post)
					return;
				
				var $hover = $('#post-hover-' + id + '[data-board="' + board + '"]');
				if($hover.length == 0)
					return;

				var scrollTop = $(window).scrollTop();
				if ($link.is("[data-thread]")) scrollTop = 0;
				var epy = e.pageY;
				if ($link.is("[data-thread]")) epy -= $(window).scrollTop();

				var top = (epy ? epy : hovered_at['y']) - 10;
				
				if(epy < scrollTop + 15) {
					top = scrollTop;
				} else if(epy > scrollTop + $(window).height() - $hover.height() - 15) {
					top = scrollTop + $(window).height() - $hover.height() - 15;
				}
				
				/* 			
				var hovery = e.pageY ? e.pageY : hovered_at['y'];
				if ( ( hovery - top) > 20){
					top = hovery;
				}
				*/
				
				$hover.css('left', (e.pageX ? e.pageX : hovered_at['x']) + 1).css('top', top);
			});
		};
		
		$('div.body a:not([rel="nofollow"])').each(init_hover);
		$('.mentioned > a').each(init_hover);
		// allow to work with auto-reload.js, etc.
		$(document).on('new_post', function(e, post) {
			$(post).find('div.body a:not([rel="nofollow"])').each(init_hover);
			$(post).find('.mentioned > a').each(init_hover);
		});
	}
});


