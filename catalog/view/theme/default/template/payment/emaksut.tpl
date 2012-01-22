<form action="<?php echo $action; ?>" method="post" id="payment">
<?
$vars = get_defined_vars();
foreach ($vars as $key => $value) {
  if (substr($key, 0, 4) != 'pmt_') continue;
?>
  <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>" />
<?
}
?>
</form>

<div class="buttons">
  <div class="right"><a onclick="$('#payment').submit();" class="button"><span><?php echo $button_confirm; ?></span></a></div>
</div>
