@include('includes.headcss')
<link href="../plugins/bower_components/switchery/dist/switchery.min.css" rel="stylesheet" />
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Device Check</h4> 
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table id="example" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="font-weight-bold">#</th>
                            <th class="font-weight-bold">Minimum Requirement</th>
                            <th class="your-device-txt font-weight-bold">This Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Operating System: Windows, Mac, iOS, Android, Linux</td>
                            <td><div class="txt-block" id="os-type-status"><i class="ti-check text-success"></i></div><div id="os-type-device" class="your-device-txt txt-block">Windows</div></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Browser: Chrome 11+, IE 9+, Firefox 5+, Safari 4+</td>
                            <td><div class="txt-block" id="browser-type-status"><i class="ti-check text-success"></i></div><div id="browser-type-device" class="your-device-txt txt-block">Chrome 78</div></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Cookies Enabled</td>
                            <td><div class="txt-block" id="cookie-status"><i class="ti-check text-success"></i></div><div id="cookie-device" class="your-device-txt txt-block">Enabled</div></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Javascript Enabled</td>
                            <td><div class="txt-block" id="js-status"><i class="ti-check text-success"></i></div><div id="js-device" class="your-device-txt txt-block">Enabled</div></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>HTML5 Supported</td>
                            <td><div class="txt-block" id="html5-status"><i class="ti-check text-success"></i></div><div id="html5-device" class="your-device-txt txt-block">Supported</div></td>
                        </tr>
                        <!-- <tr>
                                <td>6</td>
                                <td>Whitelist URL: <span>*.edNexus.io <a class="modalLnk" href="https://ajax.ednexus.io/v1/kmap/lib/require-min.js">What's this?</a></span></td>
                                <td><div id="edNexus-res" class="domain-avail txt-block" data-url="" data-type="script"><i class="ti-check text-success"></i></div><div id="edNexus-res-device" class="your-device-txt txt-block">Accessible</div>
                        </td></tr>
                        <tr>
                                <td>7</td>
                                <td>Whitelist URL: <span>*.jwpsrv.com and *.jwpcdn.com <a class="modalLnk" href="https://assets-jpcust.jwpsrv.com/watermarks/CXIvWNEK.png">What's this?</a></span></td>
                                <td><div id="video-res" class="domain-avail txt-block" data-type="script"><i class="ti-check text-success"></i></div><div id="video-res-device" class="your-device-txt txt-block">Accessible</div>
                        </td></tr>
                        <tr>
                                <td>8</td>
                                <td>Whitelist URL: <span>MathJax (*.cloudflare.com) <a class="modalLnk" href="https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.2.0/MathJax.js?config=TeX-AMS_HTML">What's this?</a></span></td>
                                <td><div id="mathjax-res" class="domain-avail txt-block" data-type="script"><i class="ti-check text-success"></i></div><div id="mathjax-res-device" class="your-device-txt txt-block">Accessible</div>
                        </td></tr> -->                        
                    </tbody>
                </table>
            </div>    
        </div>
    </div>
</div>

@include('includes.footerJs')

<script src="../plugins/bower_components/switchery/dist/switchery.min.js"></script>
<script type="text/javascript" src="../js/detect.js?v=22082018"></script>
<script>
    jQuery(document).ready(function() {
    	if(!browserCheck()){
	    	setDeviceCheckCookie();
    	};

    	$("a.modalLnk").fancybox({
            'titlePosition'     : 'inside',
            'transitionIn'      : 'none',
            'transitionOut'     : 'none',
            'scrolling'         : 'no',
            closeClick: false,
            helpers   : { overlay : {closeClick: false}  }
        });		
    });
</script>

<script>
        $(function() {
            // Switchery
            var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
            $('.js-switch').each(function() {
                new Switchery($(this)[0], $(this).data());
            });
            // For select 2
            $(".select2").select2();
            $('.selectpicker').selectpicker();
            //Bootstrap-TouchSpin
            $(".vertical-spin").TouchSpin({
                verticalbuttons: true
            });
            var vspinTrue = $(".vertical-spin").TouchSpin({
                verticalbuttons: true
            });
            if (vspinTrue) {
                $('.vertical-spin').prev('.bootstrap-touchspin-prefix').remove();
            }
            $("input[name='tch1']").TouchSpin({
                min: 0,
                max: 100,
                step: 0.1,
                decimals: 2,
                boostat: 5,
                maxboostedstep: 10,
                postfix: '%'
            });
            $("input[name='tch2']").TouchSpin({
                min: -1000000000,
                max: 1000000000,
                stepinterval: 50,
                maxboostedstep: 10000000,
                prefix: '$'
            });
            $("input[name='tch3']").TouchSpin();
            $("input[name='tch3_22']").TouchSpin({
                initval: 40
            });
            $("input[name='tch5']").TouchSpin({
                prefix: "pre",
                postfix: "post"
            });
            // For multiselect
            $('#pre-selected-options').multiSelect();
            $('#optgroup').multiSelect({
                selectableOptgroup: true
            });
            $('#public-methods').multiSelect();
            $('#select-all').click(function() {
                $('#public-methods').multiSelect('select_all');
                return false;
            });
            $('#deselect-all').click(function() {
                $('#public-methods').multiSelect('deselect_all');
                return false;
            });
            $('#refresh').on('click', function() {
                $('#public-methods').multiSelect('refresh');
                return false;
            });
            $('#add-option').on('click', function() {
                $('#public-methods').multiSelect('addOption', {
                    value: 42,
                    text: 'test 42',
                    index: 0
                });
                return false;
            });
        });
        </script>

<script>
    function checkPassword()
    {
        var password = document.getElementById('password').value;
        var confirmpassword = document.getElementById('confirmpassword').value;

        if(password != confirmpassword)
        {
            document.getElementById('errorbox').style.display = "block";
            return false;
        }else{
            document.getElementById('errorbox').style.display = "none";
            return true;
        }
    }
</script>
<script>
$(document).ready(function () {
    $('#example').DataTable();
});
</script>
@include('includes.footer')
