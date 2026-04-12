<?php
namespace GT\DomTemplate;

enum TableDataStructureType {
	case NORMALISED;
	case DOUBLE_HEADER;
	case ASSOC_ROW;
	case HEADER_VALUE_LIST;
}
