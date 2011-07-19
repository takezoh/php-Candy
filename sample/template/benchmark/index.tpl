{config_load file="test.conf" section="setup"}
${document(header.tpl)}

<PRE>

<!-- bold and title are read from the config file -->
{if #bold#}<b>{/if}
<!-- capitalize the first letters of each word of the title -->
Title: {#title#|capitalize}
{if #bold#}</b>{/if}

The current date and time is ${now("%Y-%m-%d %H:%M:%S")}

The value of global assigned variable $SCRIPT_NAME is ${$SCRIPT_NAME}

Example of accessing server environment variable SERVER_NAME: ${$_SERVER[SERVER_NAME]}

The value of \${$Name} is <b>${$Name}</b>

variable modifier example of \${upper($Name)}

<b>${upper($Name)}</b>


An example of a section loop:

<div php:foreach="$FirstName as $outer => $value">
	<span php:if="$outer < 2" php:replace="($outer + 1) . ' * ' . $FirstName[$outer] . ' ' . $LastName[$outer]"></span>
	<span php:else="" php:replace="($outer + 1) . ' . ' . $FirstName[$outer] . ' ' . $LastName[$outer]"></span>
</div>
<div php:foreachelse="">
	none
</div>

An example of section looped key values:

<div php:foreach="$contacts as $sec1 => $value">
	phone: ${$contacts[$sec1][phone]}<br>
	fax: ${$contacts[$sec1][fax]}<br>
	cell: ${$contacts[$sec1][cell]}<br>
</div>
<p>

</PRE>

This is an example of the html_select_date function:

<form>
{html_select_date start_year=1998 end_year=2010}
</form>

This is an example of the html_select_time function:

<form>
{html_select_time use_24_hours=false}
</form>

This is an example of the html_options function:

<form>
<select name=states>
{html_options values=$option_values selected=$option_selected output=$option_output}
</select>
</form>

${document(footer.tpl)}
