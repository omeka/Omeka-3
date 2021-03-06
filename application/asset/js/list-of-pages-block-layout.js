(function ($) {
    function loadJStree(index) {
        
        //Initialize unique jsTree for each block
        var navTree = $("[name='o:block[" + index + "][o:layout]']").siblings('.block-pagelist-tree');
        var initialTreeData;
        navTree.jstree({
            'core': {
                "check_callback" : function (operation, node, parent, position, more) {
                    if(operation === "copy_node" || operation === "move_node") {
                        if(more.is_multi) {
                            return false; // prevent moving node to different tree
                        }
                    }
                    return true; // allow everything else
                },
                'data': navTree.data('jstree-data'),
            },
            'plugins': ['dnd', 'removenode', 'display']
        }).on('loaded.jstree', function() {
            // Open all nodes by default.
            navTree.jstree(true).open_all();
            initialTreeData = JSON.stringify(navTree.jstree(true).get_json());
        }).on('move_node.jstree', function(e, data) {
            // Open node after moving it.
            var parent = navTree.jstree(true).get_node(data.parent);
            navTree.jstree(true).open_all(parent);
        });

        $('#site-form').on('o:before-form-unload', function () {
            if (initialTreeData !== JSON.stringify(navTree.jstree(true).get_json())) {
                Omeka.markDirty(this);
            }
        });
    }

    $(document).ready(function () {
        var list = document.getElementById('blocks');
        var blockIndex = 0;
        var jstreeIndex = 1;

        $('#blocks .block').each(function () {
            loadJStree(blockIndex);
            blockIndex++;
        });

        $('#blocks').on('o:block-added', '.block', function () {
            loadJStree(blockIndex);
            blockIndex++;
        });
        
        $('form').submit(function(e) {
            $('#blocks .block').each(function(blockIndex) {
                var thisBlock = $(this);
                if (thisBlock.attr('data-block-layout') === 'listOfPages') {
                    // Update listOfPages jstree object
                    // Increment if multiple
                    var jstree = thisBlock.find('.jstree-' + jstreeIndex).jstree()
                    thisBlock.find('.jstree-' + jstreeIndex + ' .jstree-node').each(function(index, element) {
                        //Remove deleted nodes and any children
                        if (element.classList.contains('jstree-removenode-removed')) {
                            jstree.delete_node(element);
                        }; 
                        if (jstree.get_node(element)) {
                            var nodeObj = jstree.get_node(element);
                            var element = $(element);
                            nodeObj.data['data'][element.data('name')] = element.val()
                        };
                    });
                    thisBlock.find('.jstree-' + jstreeIndex).siblings('.inputs').find(':input[type=hidden]').val(JSON.stringify(jstree.get_json()));
                    jstreeIndex++;
                }
            });
        });
    });
})(window.jQuery);