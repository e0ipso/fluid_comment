import React from 'react';

import FluidCommentForm from './FluidCommentForm';

const FluidCommentAction = (
    { name, title, subject, body, handleEdit, handleConfirm, handleCancel, formKey, isRefreshing }
  ) => (
  <div className="comment__content">
    {name === 'update'
      ? <FluidCommentForm
          formKey
          key={formKey}
          handleSubmit={handleEdit}
          handleCancel={handleCancel}
          isRefreshing={isRefreshing}
          values={{ subject, body }}
        />
      : <ul className="links inline">
          <li>{title}</li>
          <li>
            <button className="button" onClick={handleConfirm}>Confirm</button>
            <button className="button" onClick={handleCancel}>Cancel</button>
          </li>
        </ul>
    }
  </div>
);

export default FluidCommentAction;
