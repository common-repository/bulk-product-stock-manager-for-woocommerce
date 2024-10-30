jQuery(document).ready(function($) {
    $("#idOSGBPSM").click(function(e) {
        if (confirm("Are you sure you want to process this?")) {
            e.preventDefault();
            var prams = new Array();
            $("input[name='mychecky']:checked").each(function(i) {
                prams.push($(this).val());
            });

            var remarks = $('#osgRemarkfield').val();
            var osgsetValue = $('#osgQuantityField').val();
            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                data: { action: 'my_action', param: prams, pramRemarks: remarks, osgpramVal: osgsetValue, osgbpsm_nonce: osgbpsmScriptAjax.nonce },
                beforeSend: function() {
                    $("#loaderOsgDiv").show();
                },
            }).done(function(msg) {
                $("#divOSGBPSM").html(msg);
                $("#divOSGBPSM").addClass("alertBlue");
                $("#loaderOsgDiv").hide();
            });
        } else {
            return false;
        }
    });
});


jQuery(document).ready(function($) {
    $("button[id^='osgbpsmRestore_']").click(function(e) {
        if (confirm("Are you sure you want to restore this?")) {
            e.preventDefault();
            var catchID = this.id;
            var restoreID = catchID.replace("osgbpsmRestore_", "");
            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                data: { action: 'osg_processrestore', restoreCatchID: restoreID, osgbpsm_nonce: osgbpsmScriptAjax.nonce },
                beforeSend: function() {
                    $("#loaderOsgDiv").show();
                },
            }).done(function(msg) {
                $("#divOSGBPSM").html(msg);
                $("#divOSGBPSM").addClass("alertBlue");
                $("#loaderOsgDiv").hide();
            });
        } else {
            return false;
        }
    });
});