function getbookList(obj) {
    jQuery(obj).autocomplete({
        source: function (request, response) {
            jQuery.ajax({
                url: '../Ajax.php',
                dataType: 'json',
                data: {
                    name_startsWith: request.term,
                    action: 'getBookName',
                    bookfield: jQuery('#bookfield').val(),
                },
                success: function (data) {
                    // alert(data);
                
                    response (data);
                    // response(jQuery.map(data, function (item) {
                    //     var code = item;
                    //     return {
                    //         label: code,
                    //         value: code,
                    //         data: item
                    //     }
                    // }));
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) { 
                    alert("Status: " + textStatus); alert("Error: " + errorThrown); 
                }
            });
        },
        autoFocus: true,
        minLength: 2,
        select: function (event, ui) {
            var names = ui.item.data;
            jQuery('.bookskeywords').val(names);

        }
    });

}