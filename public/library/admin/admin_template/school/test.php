<style>
.sub-menu {
    display:none
}
</style>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<ul id="menu">
    <li><a href="#">1</a></li>
    <li><a href="#">2</a>
        <ul class="sub-menu">
            <li><a href="#">2.1</a></li>
            <li><a href="#">2.2</a></li>
        </ul>
    </li>
    <li><a href="#">3</a>
        <ul class="sub-menu">
            <li><a href="#">3.1</a>
            </li>
        </ul>
    </li>
    <li><a href="#">4</a>
    </li>
    <li><a href="#">5</a>
    </li>
    <li><a href="#">6</a>
    </li>
</ul>
<script>

$("#menu > li > a").click(function () {
    $('ul.sub-menu').not($(this).siblings()).slideUp();
    $(this).siblings("ul.sub-menu").slideToggle();
});

</script>