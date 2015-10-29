/**
 * For menu function add, edit and copy
 *
 * @author Nguyen Huu Tam
 * @copyright Kobe Digital Labo, Inc
 * @since 2012/07/25
 */

var jcrop_api;

var hideSelectedArea = false, isDragging = false, isResizing = false, selectNewRow = false, isEditMode = false, editedGroup = 0;
var mX, mY, rX, rY, rW, rH;
var currentList = '';
var selectedRows = null;
var currentGroup = 0, selectedRowIndex = 0, currentProductSelectedRowIndex = -1, currentLinkSelectedRowIndex = -1, currentVideoSelectedRowIndex = -1;
var allExistedProductVideo = new Array();

function initJcrop(done){
    $(menuImage).Jcrop({
        boxWidth: JCropWidth,
        touchSupport: true,        
        onSelect: updateCoords,
		onRelease: clearSelected
    },function(){
        jcrop_api = this;
    });
};

$(document).ready(function() {
    $("#main").css('width', getWidth()+50);
    $("#wrapper").css('min-width', 1240);
	$('#no_image_holder img').css('padding-top', $('#no_image_holder').height() / 2 - $('#no_image_holder img').height() / 2);
	$(":input[placeholder]").placeholder();
	
	currentGroup = maxGroup;
	currentList = productList
	
	$('#rightTabs').tabs({
		onSelect:function(title){
			if (title === '商品' && $('#search').val() === '') {
				$('#search').focus();
				$('#search').blur();
			}
			$('#current_tab').val(title);
		}
    });
	
	if ($('#current_tab').val() !== '') {
		$('#rightTabs').tabs('select', $('#current_tab').val());
	}
	
	$(productList).datagrid({
		onSelect: function(rowIndex, rowData) {
			currentList = productList;
			if (isResizing) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				releaseJcrop();
				return;
			}
			if (isEditMode) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				setTimeout(function(){$(productList).datagrid('unselectRow', rowIndex);}, 50);
				return;
			}
			
			setTimeout(function(){selectedRows = $(currentList).datagrid('getSelections');}, 50);

			if (rowIndex === currentProductSelectedRowIndex) {
				setTimeout(function(){$(productList).datagrid('unselectRow', rowIndex);}, 50);
				currentProductSelectedRowIndex = -1;
			} else {
				currentProductSelectedRowIndex = rowIndex;
			}
		}
	});
	
	$(linkList).datagrid({
		onSelect: function(rowIndex, rowData) {
			currentList = linkList;
			if (isResizing) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				releaseJcrop();
				return;
			}
			if (isEditMode) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				setTimeout(function(){$(linkList).datagrid('unselectRow', rowIndex);}, 50);
				return;
			}
			
			setTimeout(function(){selectedRows = $(currentList).datagrid('getSelections');}, 50);
			
			if (rowIndex === currentLinkSelectedRowIndex) {
				setTimeout(function(){$(linkList).datagrid('unselectRow', rowIndex);}, 50);
				currentLinkSelectedRowIndex = -1;
			} else {
				currentLinkSelectedRowIndex = rowIndex;
			}
		}
	});
	
	$(videoList).datagrid({
		onSelect: function(rowIndex, rowData) {
			currentList = videoList;
			if (isResizing) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				releaseJcrop();
				return;
			}
			if (isEditMode) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				setTimeout(function(){$(videoList).datagrid('unselectRow', rowIndex);}, 50);
				return;
			}
			
			setTimeout(function(){selectedRows = $(currentList).datagrid('getSelections');}, 50);
			
			if (rowIndex === currentVideoSelectedRowIndex) {
				setTimeout(function(){$(videoList).datagrid('unselectRow', rowIndex);}, 50);
				currentVideoSelectedRowIndex = -1;
			} else {
				currentVideoSelectedRowIndex = rowIndex;
			}
		}
	});
	
	$(menuOthersList).datagrid({
		onSelect: function(rowIndex, rowData) {
			currentList = menuOthersList;
			if (isResizing) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				releaseJcrop();
				return;
			}
			if (isEditMode) {
				selectNewRow = true;
				selectedRowIndex = rowIndex;
				setTimeout(function(){$(menuOthersList).datagrid('unselectRow', rowIndex);}, 50);
				return;
			}
			
			setTimeout(function(){selectedRows = $(currentList).datagrid('getSelections');}, 50);
			
			if (rowIndex === currentLinkSelectedRowIndex) {
				setTimeout(function(){$(menuOthersList).datagrid('unselectRow', rowIndex);}, 50);
				currentLinkSelectedRowIndex = -1;
			} else {
				currentLinkSelectedRowIndex = rowIndex;
			}
		}
	});
	
	//$(txtSearch).bind('input propertychange', searchData);
	$(txtSearch).on('keyup paste', function() {
		searchData();
	});
	
	$(menuImage).Jcrop({
        boxWidth: JCropWidth,
        touchSupport: true,        
        onSelect: updateCoords,
		onRelease: clearSelected
    },function(){
        jcrop_api = this;
		jcrop_api.disable();
		
		$('.jcrop-holder').droppable({
			onDrop:function(e, source) {
				var rows = $(currentList).datagrid('getSelections');
				if (rows.length > 0) {					
					setTimeout(function(){isDragging = false;}, 50);
					$('.selected-area').hide();
					var scale = jcrop_api.getScaleFactor();
					setJcropSelected((mX - 50) * scale[0], (mY - 50) * scale[1], (mX + 50) * scale[0], (mY + 50) * scale[1]);
				}
			}
		});
		$('.jcrop-holder').mousemove(function(event) {
			var offset = $(this).offset();
			mX = event.pageX - offset.left;
			mY = event.pageY - offset.top;
		});
		
		//save all existed products, links and videos
		$.ajax({
			type: "GET",
			url: urlAllList,
			success: function(response) {
				if (response.message != undefined) {
					
				} else {
					$.each(response.rows, function(i, item) {
						allExistedProductVideo[allExistedProductVideo.length] = [item.id, item.name];
					});

					var scale = jcrop_api.getScaleFactor();
					$('input[name="lastSelectedArea"]').each(function() {
						var group = $(this).attr('group');
						var productIds = $(this).attr('productIds').split(';@;');
						var productNames = $(this).attr('productNames').split(';@;');
						var videoIds = $(this).attr('videoIds').split(';@;');
						var productTypes = $(this).attr('types').split(';');
						var target = $(this).attr('target').split(',');
						var content = '';
						if ($(this).attr('productIds') != '') {
							for (var i = 0; i < productIds.length; i++) {
								productNames[i] = productNames[i].replace(/</g, '&lt;');
								productNames[i] = productNames[i].replace(/>/g, '&gt;');
								if (checkProduct(productIds[i], productNames[i])) {
									if (productTypes[i] == 'product') {
										content += '<li>' + productIds[i] + ':' + productNames[i] + '</li>';
									} else {
										content += '<li>' + productNames[i] + '</li>';
									}
								} else {
									if (productTypes[i] == 'product') {
										content += '<li style="color:red">' + productIds[i] + ':' + productNames[i] + '</li>';
									} else {
										var info = productIds[i].split(':');
										if (info[0] == 'goMenuSet') {
											deleteSelectedAreaById(group);
										}
										content += '<li style="color:red">' + (productNames[i] == "" ? productIds : productNames[i]) + '</li>';
									}
								}
							}
						}
						if ($(this).attr('videoIds') != '') {
							for (var i = 0; i < videoIds.length; i++) {						
								if (checkProduct('', videoIds[i])) {
									content += '<li>' + videoIds[i] + '</li>';
								} else {
									content += '<li style="color:red">' + videoIds[i] + '</li>';
								}
							}
						}
						createSelection(content, group, target[0] / scale[0], target[1] / scale[1], target[2] / scale[0], target[3] / scale[1]);
					});
					$('input[name="lastSelectedArea"]').remove();
				}
			}
		});	
		
	});
	
	$('body').bind('click', function(e) {
		if (!isDragging && isResizing && e.target != null 
			&& $(e.target).attr('class') !== 'jcrop-tracker' 
			&& !$(e.target).hasClass('jcrop-handle')
			&& $(e.target).attr('class') !== 'selected-area-edit'
			&& $(e.target).parent().attr('class') !== 'selected-area-lines') {
			releaseJcrop();
		}
		isDragging = false;
    });
});

function initDragging(id_table) {	
	$(id_table).datagrid('getPanel').find('.datagrid-body').draggable({
		handle:'.datagrid-btable',
		revert:true,
		revertDuration: 0,
		deltaX:10,
		deltaY:10,
		cursor: "default",
		proxy:function(source) {
			var rows = $(id_table).datagrid('getSelections');
			if (rows.length == 0) {
				return $('<div></div>');
			}
			var content = '';
			for (var i = 0; i < rows.length; i++) {
				if (i == rows.length - 1) {
					content += '<li>' + rows[i].name + '</li>';
				} else {
					content += '<li style="border-bottom:solid 1px white;">' + rows[i].name + '</li>';
				}
			}
			var p = $('<div id="drag-proxy" style="z-index:99999;background:#e5e5e5;filter:alpha(opacity=90);-moz-opacity:0.9;opacity: 0.9;border:1px solid #c0c0c0;padding-right:10px"></div>').appendTo('body');
			p.html('<ul style="padding-left: 5px;list-style-type: none;">' + content + '</ul>');
			p.hide();			
			return p;
		},
		onDrag: function(e) {
			isDragging = true;
			$("body").addClass("move");
			var x1=e.pageX,y1=e.pageY,x2=e.data.startX,y2=e.data.startY;
			var d = Math.sqrt((x1-x2)*(x1-x2)+(y1-y2)*(y1-y2));
			if (d > 3) {	// when drag a little distance, show the proxy object
				if ($(this).draggable('proxy')) {
					$(this).draggable('proxy').show();			
				}
			}
		},
		onStopDrag:function(e) {
			$("body").removeClass("move");
			if ($(this).draggable('proxy')) {
				$(this).draggable('proxy').remove();
			}
		}
	});	
}

function checkProduct(id, name) {
	for (var i = 0; i < allExistedProductVideo.length; i++) {
		if (id == allExistedProductVideo[i][0] && name == allExistedProductVideo[i][1]) {
			return true;
		}
	}
	return false;
}

function searchData() {
	$(productList).datagrid({
		url: urlProductList + '/q/' + encodeURIComponent($(txtSearch).val())
	});
}

function setJcropSelected(x1, y1, x2, y2){
    initJcrop();
	if (jcrop_api == null) {
		return;
	} else {
		jcrop_api.enable();
	}
	isResizing = true;
    jcrop_api.setSelect([x1, y1, x2, y2]);
}

function updateCoords(c) {
	if (!hideSelectedArea) {
		$('.selected-area').hide();
		hideSelectedArea = true;
	}
	rX = Math.round(c.x);
    rY = Math.round(c.y);
    rW = Math.round(c.w);
    rH = Math.round(c.h);
}

function releaseJcrop() {
    if (jcrop_api !== undefined) {
        jcrop_api.release();		
    }
}

function getTargetInput(target) {
    return $('td[field="' + target + '"] .datagrid-editable-input');
}

function clearSelected() {
	isResizing = false;
	jcrop_api.disable();
	setTimeout(function(){$('.jcrop-tracker').css('cursor', 'default');}, 50);
	if (isEditMode) {
		saveData('', '', '', '', '');
		return;
	}
	$('.selected-area').show();
	hideSelectedArea = false;
	var rows = selectedRows;//$(currentList).datagrid('getSelections');
	if (rows.length > 0) {
		var content = '';
		var productIds = '';
		var productNames = '';
		var productTypes = '';
		var videoIds = '';
		var ids = '';
		for (var i = 0; i < rows.length; i++) {			
			if (rows[i].id != '') {
				if (productIds == '') {
					productIds = rows[i].id;
					productNames = rows[i].name;
					productTypes = rows[i].type;
				} else {
					productIds += ';@;' + rows[i].id;
					productNames += ';@;' + rows[i].name;
					productTypes += ';@;' + rows[i].type;
				}
			} else {
				if (videoIds == '') {
					videoIds = rows[i].name;
				} else {
					videoIds += ';@;' + rows[i].name;
				}
			}
		}
		//product
		if (productIds != '') {
			var arrayProductIds = productIds.split(';@;');
			var arrayProductNames = productNames.split(';@;');
			var arrayProductTypes = productTypes.split(';@;');
			for (var i = 0; i < arrayProductIds.length; i++) {
				if (arrayProductTypes[i] == 'product') {
					content += '<li>' + arrayProductIds[i] + ':' + arrayProductNames[i] + '</li>';
				} else {
					content += '<li>' + arrayProductNames[i] + '</li>';
				}
			}
		}
		//video
		if (videoIds != '') {
			var arrayVideoIds = videoIds.split(';@;');
			for (var i = 0; i < arrayVideoIds.length; i++) {
				content += '<li>' + arrayVideoIds[i] + '</li>';
			}
		}

		saveData(productIds, productNames, productTypes, videoIds, content);
	}
};

function deleteSelectedArea(obj) {
	$.ajax({
        type: "GET",
        data: {
            'del-product':$(obj).attr('group')
        },
        url: urlUpdateProduct,
        success: function(response) {
			if (response.message != undefined) {
                alert(response.message);
            } else {
				$(obj).parent().parent().remove();
			}
        }
    });	
};

function deleteSelectedAreaById(id) {
	$.ajax({
        type: "GET",
        data: {
            'del-product':id
        },
        url: urlUpdateProduct,
        success: function(response) {
			if (response.message != undefined) {
                alert(response.message);
            }
        }
    });	
};

function saveData(productIds, productNames, productTypes, videoIds, contentHTML) {
	var group = 1;
	if (isEditMode) {
		group = editedGroup;
	} else {
		currentGroup++;
		group = currentGroup;
	}
    $.ajax({
        type: "GET",
        data: {
            'update-group':isEditMode ? '1' : '0',
            'update-product-ids':productIds,
            'update-product-names':productNames,
            'update-product-types':productTypes,
            'update-video-ids':videoIds,
            'target':rX + ',' + rY + ',' + rW + ',' + rH,
            'group':group
        },
        url: urlUpdateProduct,
        success: function(response) {
			if (response.message != undefined) {
                alert(response.message);
            } else {
				var scale = jcrop_api.getScaleFactor();
				if (isEditMode) {
					isEditMode = false;
					$('#selected_area_' + editedGroup).css({
						'left': rX / scale[0],
						'top': rY / scale[1],
						'width': rW / scale[0],
						'height': rH / scale[1]
					});
					$('.selected-area').show();
					hideSelectedArea = false;
				} else {								
					createSelection(contentHTML, currentGroup, rX / scale[0], rY / scale[1], rW / scale[0], rH / scale[1]);
					$(currentList).datagrid('unselectAll');
				}
				if (selectNewRow) {
					setTimeout(function(){$(currentList).datagrid('selectRow', selectedRowIndex);}, 50);
				}
				selectNewRow = false;
			}
        }
    });
}

function createSelection(contentHTML, group, x, y, w, h) {
	$('<div id="selected_area_' + group + '" class="selected-area" style="cursor:default;left:' + x + 'px;top:' + y + 'px;width:' + w + 'px;height:' + h + 'px"><div class="selected-area-delete"><img onclick="deleteSelectedArea(this)" src="/images/del.png" group="' + group + '" style="width:20px;height:20px;border:none"/></div><div onclick="editSelectedArea(this)" class="selected-area-edit"><ul class="selected-area-lines" style="color:white">' + contentHTML + '</ul></div></div>').appendTo('.jcrop-holder');
}

function editSelectedArea(obj) {
	jcrop_api.enable();
	isEditMode = true;
	editedGroup = $(obj).parent().find('img').attr('group');
	$('.selected-area').hide();
	$(productList).datagrid('unselectAll');
	$(linkList).datagrid('unselectAll');
	$(videoList).datagrid('unselectAll');
	
	var x = parseInt($(obj).parent().css('left').replace('px', ''));
	var y = parseInt($(obj).parent().css('top').replace('px', ''));
	var w = parseInt($(obj).parent().css('width').replace('px', ''));
	var h = parseInt($(obj).parent().css('height').replace('px', ''));
	var scale = jcrop_api.getScaleFactor();
	setJcropSelected(x * scale[0], y * scale[1], (x + w) * scale[0], (y + h) * scale[1]);
};