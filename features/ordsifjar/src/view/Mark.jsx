/** "Tímaflækingur · Fortíð" -> its two parts and the class for the time's colour. */
const TIME = { 'Fortíð': 'is-fortid', 'Framtíð': 'is-framtid' };

/**
 * The marking for a special kind of vasaskrímsli: a yellow pill for the kind and, after a slanted cut, the
 * time in its own colour (Block::mark_html() writes the same for the page without JavaScript).
 */
export default function Mark( { serstakt } ) {
	if ( ! serstakt ) {
		return null;
	}
	const [ kind, time ] = serstakt.split( ' · ' );
	if ( ! time ) {
		return <span className="vs-of-mark">{ kind }</span>;
	}
	return (
		<span className={ `vs-of-mark is-split ${ TIME[ time ] ?? '' }` }>
			<span className="vs-of-mark__kind">{ kind }</span>{ ' ' /* Read aloud as two words; a flex box hides it. */ }
			<span className="vs-of-mark__time">{ time }</span>
		</span>
	);
}
