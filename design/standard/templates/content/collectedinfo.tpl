<h1>Collected info</h1>

<h2>{$collection.object.name}</h2>


{section loop=$collection.attributes}

<h3>{$:item.contentclass_attribute_name}</h3>

{attribute_result_gui attribute=$:item}

{/section}
