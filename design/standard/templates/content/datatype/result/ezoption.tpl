{let total_count=fetch('content','collected_info_count', hash( 'object_attribute_id', $attribute.contentobject_attribute_id ) )
     item_counts=fetch('content','collected_info_count_list', hash( 'object_attribute_id', $attribute.contentobject_attribute_id  ) ) }
<table width="500" cellspacing="0">
<tr>

{$:attribute.contentobject_attribute.content.name}

{section name=Option loop=$:attribute.contentobject_attribute.content.option_list}
<td>
{$:item.value}
</td>
<td>
<table width="300">
<tr>
{let item_count=0}
{section show=is_set($item_counts[$:item.id])}
  {set item_count=$item_counts[$:item.id]}
{/section}
<td bgcolor="ffff00" width="{div(mul($:item_count,300),$total_count)}">
&nbsp;
</td>
<td bgcolor="eeeeee" width="{sub(300,div(mul($:item_count,300),$total_count))}">

</td>
{/let}

</tr>
</table>
</td>
{delimiter}
</tr>
<tr>
{/delimiter}

{/section}
</tr>
</table>
Total: {$total_count}

{/let}
