(function() {
    tinymce.PluginManager.add('canva_tinymce4', function(editor, url) {
        var cssId, toolbarActive = false;

        function canvaImage(img) {
            var frame, callback, metadata;
            if (typeof wp === 'undefined' || !wp.media) {
                editor.execCommand('mceImage');
                return;
            }
            metadata = extractImageData(img);

            jQuery.post(canva_ajax.url, {
                action: 'canva_get_design_id',
                attachment_id: metadata.attachment_id,
                metadata: metadata,
                _ajax_nonce: canva_ajax.ajaxnonce
            }, function(str) {
                canva.api.load({
                    key: canva_ajax.canvaapikey
                }, onCanvaApiLoaded);

                 function onCanvaApiLoaded() {
                    canva.api.designer.edit({
                        'design': str
                    }, onCanvaExport);
                }
                /**
                 * So Justin has update this function to allow canva to
                 * edit existing canve deisgns and send them back
                 *
                 * This just updates the HTML code that is in the tiny MCE window
                 *
                 **/
                function onCanvaExport(exportUrl, designId) {
                    window.canvaAnimShow();
                    var data = {
                        action: "canva_mce_edit_design_action",
                        ajaxnonce: canva_ajax.ajaxnonce,
                        canvaimageurl: exportUrl,
                        canvadesignid: designId
                    };
                    jQuery.post(canva_ajax.url, data, function(result) {
                        var time = jQuery.now(),
                            replace1 = '.png?' + time,
                            strNewString = editor.getContent();
                        // replace any .png or .png?92938293928 or .png?92938293928?232323?34343
                        strNewString = strNewString.replace(/\.png([\?0-9]*)/g, replace1);
                        editor.setContent(strNewString);
                        jQuery("#canvamask").fadeOut(500, function() {
                            jQuery(this).remove();
                        });
                        jQuery("#canvamask").remove();
                    });
                }

               
            });
        }

        function removeImage(img) {
            
        }

        function editImage(img) {
            
        }
        editor.on('mouseup', function(event) {
            var image,
                node = event.target,
                dom = editor.dom;
            // Don't trigger on right-click
            if (event.button && event.button > 1) {
                return;
            }
            
            if (node.nodeName === 'I' && dom.getParent(node, '#wp-image-toolbar')) {
                image = dom.select('img[data-wp-imgselect]')[0];
                if (image) {
                    editor.selection.select(image);
                    if (dom.hasClass(node, 'remove')) {
                        removeImage(image);
                    } else if (dom.hasClass(node, 'edit')) {
                        editImage(image);
                    } else if (dom.hasClass(node, 'canva')) {
                        canvaImage(image);
                    }
                }
            } else if (node.nodeName === 'IMG' && !isPlaceholder(node)) { //!editor.dom.getAttrib(node, 'data-wp-imgselect') &&
                addToolbar(node);
            } else if (node.nodeName !== 'IMG') {
                removeToolbar();
            }
        });
       

        function addToolbar(node) {
            
            var rectangle, toolbarHtml, toolbar, left, metadata,
                dom = editor.dom;
            removeToolbar();
            // Don't add to placeholders
            if (!node || node.nodeName !== 'IMG' || isPlaceholder(node)) {
                return;
            }
            dom.setAttrib(node, 'data-wp-imgselect', 1);
            rectangle = dom.getRect(node);

            toolbarHtml = '<i class="dashicons dashicons-edit edit" data-mce-bogus="all"></i><i class="dashicons dashicons-no-alt remove" data-mce-bogus="all"></i>';
            metadata = extractImageData(node);
            
            
            jQuery.post(canva_ajax.url, {
                action: 'canva_get_design_id',
                attachment_id: metadata.attachment_id,
                metadata: metadata,
                _ajax_nonce: canva_ajax.ajaxnonce
            }, function(str) {
                
                if (str.length > 0) {
                    toolbarHtml += '<i class="dashicons dashicons-canva canva" data-mce-bogus="all"></i>';
                }
                toolbar = dom.create('div', {
                    'id': 'wp-image-toolbar',
                    'data-mce-bogus': '1',
                    'contenteditable': false
                }, toolbarHtml);
                if (editor.rtl) {
                    left = rectangle.x + rectangle.w - 82;
                } else {
                    left = rectangle.x;
                }
                editor.getBody().appendChild(toolbar);
                dom.setStyles(toolbar, {
                    top: rectangle.y,
                    left: left
                });
                toolbarActive = true;
            });
        
        }

        function removeToolbar() {
            
            var toolbar = editor.dom.get('wp-image-toolbar');
            if (toolbar) {
                editor.dom.remove(toolbar);
            }
            editor.dom.setAttrib(editor.dom.select('img[data-wp-imgselect]'), 'data-wp-imgselect', null);
            toolbarActive = false;
        }

        function isPlaceholder(node) {
            var dom = editor.dom;
            if (dom.hasClass(node, 'mceItem') || dom.getAttrib(node, 'data-mce-placeholder') || dom.getAttrib(node, 'data-mce-object')) {
                return true;
            }
            return false;
        }

        function extractImageData(imageNode) {
            var classes, extraClasses, metadata, captionBlock, caption, link, width, height,
                dom = editor.dom,
                isIntRegExp = /^\d+$/;
            // default attributes
            metadata = {
                attachment_id: false,
                size: 'custom',
                caption: '',
                align: 'none',
                extraClasses: '',
                link: false,
                linkUrl: '',
                linkClassName: '',
                linkTargetBlank: false,
                linkRel: '',
                title: ''
            };
            metadata.url = dom.getAttrib(imageNode, 'src');
            metadata.alt = dom.getAttrib(imageNode, 'alt');
            metadata.title = dom.getAttrib(imageNode, 'title');
            width = dom.getAttrib(imageNode, 'width');
            height = dom.getAttrib(imageNode, 'height');
            if (!isIntRegExp.test(width) || parseInt(width, 10) < 1) {
                width = imageNode.naturalWidth || imageNode.width;
            }
            if (!isIntRegExp.test(height) || parseInt(height, 10) < 1) {
                height = imageNode.naturalHeight || imageNode.height;
            }
            metadata.customWidth = metadata.width = width;
            metadata.customHeight = metadata.height = height;
            classes = tinymce.explode(imageNode.className, ' ');
            extraClasses = [];
            tinymce.each(classes, function(name) {
                if (/^wp-image/.test(name)) {
                    metadata.attachment_id = parseInt(name.replace('wp-image-', ''), 10);
                } else if (/^align/.test(name)) {
                    metadata.align = name.replace('align', '');
                } else if (/^size/.test(name)) {
                    metadata.size = name.replace('size-', '');
                } else {
                    extraClasses.push(name);
                }
            });
            metadata.extraClasses = extraClasses.join(' ');
            // Extract caption
            captionBlock = dom.getParents(imageNode, '.wp-caption');
            if (captionBlock.length) {
                captionBlock = captionBlock[0];
                classes = captionBlock.className.split(' ');
                tinymce.each(classes, function(name) {
                    if (/^align/.test(name)) {
                        metadata.align = name.replace('align', '');
                    }
                });
                caption = dom.select('dd.wp-caption-dd', captionBlock);
                if (caption.length) {
                    caption = caption[0];
                    metadata.caption = editor.serializer.serialize(caption).replace(/<br[^>]*>/g, '$&\n').replace(/^<p>/, '').replace(/<\/p>$/, '');
                }
            }
            // Extract linkTo
            if (imageNode.parentNode && imageNode.parentNode.nodeName === 'A') {
                link = imageNode.parentNode;
                metadata.linkUrl = dom.getAttrib(link, 'href');
                metadata.linkTargetBlank = dom.getAttrib(link, 'target') === '_blank' ? true : false;
                metadata.linkRel = dom.getAttrib(link, 'rel');
                metadata.linkClassName = link.className;
            }
            return metadata;
        }
    });
})();