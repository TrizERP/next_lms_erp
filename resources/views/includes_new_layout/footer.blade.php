		</div>
	</div>
</div>
</body>
<script>
	function setSession(item, object) {

		var xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if (this.readyState == 4 && this.status == 200) {
				location.reload();
			}
		};
		xhttp.open("GET", "{{route('setsession')}}?" + item + "=" + object, true);
		xhttp.send();
	}
</script>

</html>