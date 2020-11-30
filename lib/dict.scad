function dict(x,i,j) = (
  j?
    x[search([i],x)[0]][1][search([j],x[search([i],x)[0]][1])[0]][1] :
    x[search([i],x)[0]][1]
  );
  
