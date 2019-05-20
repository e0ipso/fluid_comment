import React from 'react';

import FluidCommentLink from './FluidCommentLink';

const FluidCommentContent = ({ id, subject, body, classes, links, permaLink, action }) => (
  <div className="comment__content">
    <h3><a href={`#${permaLink}`}>{subject}</a></h3>
    <div
      className={classes.content.join(' ')}
      dangerouslySetInnerHTML={{__html: body}}>
    </div>
    {links && <ul className="links inline">
      {links.map(link => (
        <li key={`${id}-${link.className}`} className={link.className}>
          <FluidCommentLink
            link={link}
            handleClick={(e) => action(e, link)}
          />
        </li>
      ))}
    </ul>}

  </div>
);

export default FluidCommentContent;
