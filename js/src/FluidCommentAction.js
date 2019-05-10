import React from 'react';

const FluidCommentAction = ({ title, confirm, cancel }) => (
  <div className="comment__content">
    <ul className="links inline">
      <li>{title}</li>
      <li>
        <button className="button" onClick={confirm}>Confirm</button>
        <button className="button" onClick={cancel}>Cancel</button>
      </li>
    </ul>
  </div>
);

export default FluidCommentAction;
