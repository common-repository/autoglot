(function($) {
    $(function() {
        $.ajaxSetup({ cache: false });
        
        var cnt = "";
        
        $('.autoglot_form_select_submit').change(function() {
                $(this).closest('form').submit();
            });        

        $(".toggle-editor").click(function(){
            var setId = $(this).data('id');
            $("#span_" + setId).hide();
            $("#edit_" + setId).show();
            
            $("#edit_" + setId).trigger("focus");
            cnt = $("#edit_" + setId).val();

            return false;
        });

        $(".text-editor").focusout(function(){
            var $this = $(this);
            var $alink = $this.siblings(".row-actions").find("a") 
            var url = $alink.attr("href");
            var setId = $alink.data('id');

            if(cnt != $(this).val()){

                console.log(url);
    
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: "json",
                    data: { translated: encodeURIComponent($("#edit_" + setId).val())},
                    cache: false,
                    success: function(data) {
                        console.log(data);
                        if (data) {
                            $("#span_" + setId).html($("#edit_" + setId).val());
                            $("#span_" + setId).show();
                            $("#edit_" + setId).hide();
                            $this.parents("td").css("background-color", "#ADA");
                            $alink.html("<strong style='color:#0A0;'>Updated successfully!</strong> Edit");
                        } else {
                            console.log('failed');  
                            $("#edit_" + setId).val($("#span_" + setId).html());
                            $("#span_" + setId).show();
                            $("#edit_" + setId).hide();
                            $this.parents("td").css("background-color", "#DAA");
                            $alink.html("<strong style='color:#C00;'>Failed to update!</strong> Edit");
                        }
                    },
                    error: function() {
                        console.log('error');
                        $("#edit_" + setId).val($("#span_" + setId).html());
                        $("#span_" + setId).show();
                        $("#edit_" + setId).hide();
                        $this.parents("td").css("background-color", "#DAA");
                        $alink.html("<strong style='color:#C00;'>Connection failed!</strong> Edit");
                    }
                });
            } else {
                $("#span_" + setId).show();
                $("#edit_" + setId).hide();
            }
            return true;
        });

        
        $(".delete").click(function() {
            var $this = $(this);
            var url = $this.children().attr("href");

            console.log(url);

            $.ajax({
                url: url,
                dataType: "json",
                cache: false,
                success: function(data) {
                    console.log(data);
                    if (data) {
                        $this.parents("tr").fadeOut(500);
                    } else {
                        $this.parents("tr").css("background-color", "#333");
                        $this.html("<strong style='color:red;'>Failed to delete!</strong.");
                    }
                },
                error: function() {
                    console.log('error');
                }
            });

            return false;
        });
    });
})(jQuery);